# Persistence Layer - Security Considerations

**Status**: Draft
**Created**: 2025-12-28
**Related**: [README.md](./README.md)

## Overview

Serialization and deserialization of application state introduces several security risks. This document outlines threats, mitigations, and security best practices for the Persistence Layer.

---

## Threat Model

### 1. Arbitrary Code Execution (Critical)

**Threat**: Malicious snapshot contains serialized closures or objects that execute code during deserialization.

**Attack Vector**:
```php
// Attacker-crafted snapshot
{
    "region": {
        "dispatched": [
            {
                "type": "MaliciousEvent",
                "data": {
                    "__destruct": "system('rm -rf /')"
                }
            }
        ]
    }
}
```

**Risk Level**: 🔴 **CRITICAL**

**Mitigations**:

1. **Never deserialize untrusted snapshots**
   ```php
   // ❌ DANGEROUS - untrusted input
   $snapshot = $_POST['snapshot'];
   $runtime = $persistence->restore($snapshot);

   // ✅ SAFE - validate source
   if (!$this->isAuthorizedSnapshot($snapshotId)) {
       throw new SecurityException('Unauthorized snapshot access');
   }
   $snapshot = $this->loadFromSecureStorage($snapshotId);
   $runtime = $persistence->restore($snapshot);
   ```

2. **Disable closure serialization by default**
   ```php
   // Default policy: skip closures
   $policy = new SerializationPolicy();
   $policy->skipClosures(true); // Default
   ```

3. **Validate snapshot integrity**
   ```php
   class SignedBackend implements PersistenceBackend
   {
       public function serialize(array $snapshot): string
       {
           $json = json_encode($snapshot);
           $signature = hash_hmac('sha256', $json, $this->secret);

           return json_encode([
               'data' => $json,
               'signature' => $signature
           ]);
       }

       public function deserialize(string $data): array
       {
           $envelope = json_decode($data, true);
           $expected = hash_hmac('sha256', $envelope['data'], $this->secret);

           if (!hash_equals($expected, $envelope['signature'])) {
               throw new SecurityException('Snapshot signature invalid');
           }

           return json_decode($envelope['data'], true);
       }
   }
   ```

4. **Class whitelist for deserialization**
   ```php
   class SecurePersistenceManager extends PersistenceManager
   {
       private array $allowedClasses = [
           Region::class,
           StandardRuntime::class,
           Message::class,
           // ... whitelist
       ];

       private function validateSnapshot(array $snapshot): void
       {
           $this->validateClasses($snapshot, $this->allowedClasses);
       }

       private function validateClasses(mixed $data, array $allowed): void
       {
           if (is_array($data)) {
               if (isset($data['__type']) && !in_array($data['__type'], $allowed)) {
                   throw new SecurityException(
                       "Class {$data['__type']} not allowed in snapshot"
                   );
               }

               foreach ($data as $value) {
                   $this->validateClasses($value, $allowed);
               }
           }
       }
   }
   ```

---

### 2. Object Injection (High)

**Threat**: Deserialization creates objects with malicious properties that trigger vulnerabilities.

**Attack Vector**:
```php
// Crafted snapshot exploits __wakeup(), __destruct(), etc.
{
    "context": {
        "user": {
            "__type": "User",
            "data": {
                "isAdmin": true,  // Privilege escalation
                "email": "attacker@example.com"
            }
        }
    }
}
```

**Risk Level**: 🟠 **HIGH**

**Mitigations**:

1. **Avoid using PHP's native serialize()/unserialize()**
   ```php
   // ❌ DANGEROUS
   class NativeBackend implements PersistenceBackend
   {
       public function serialize(array $snapshot): string
       {
           return serialize($snapshot); // Vulnerable to object injection
       }
   }

   // ✅ SAFE
   class JsonBackend implements PersistenceBackend
   {
       public function serialize(array $snapshot): string
       {
           return json_encode($snapshot); // No object injection
       }
   }
   ```

2. **Validate object properties on restore**
   ```php
   private function restoreContext(Region $region, array $contextData): void
   {
       foreach ($contextData as $key => $value) {
           // Validate before setting
           if ($this->isSensitiveKey($key)) {
               throw new SecurityException(
                   "Cannot restore sensitive key '{$key}' from snapshot"
               );
           }

           $validated = $this->validateValue($value);
           $this->setContextValue($region, $key, $validated);
       }
   }

   private array $sensitiveKeys = ['password', 'api_key', 'secret', 'token'];

   private function isSensitiveKey(string $key): bool
   {
       foreach ($this->sensitiveKeys as $sensitive) {
           if (str_contains(strtolower($key), $sensitive)) {
               return true;
           }
       }
       return false;
   }
   ```

3. **Use strict type validation**
   ```php
   private function validateValue(mixed $value): mixed
   {
       if (is_array($value)) {
           if (isset($value['__type'])) {
               // Only allow specific types
               if (!$this->isAllowedType($value['__type'])) {
                   throw new SecurityException(
                       "Type {$value['__type']} not allowed"
                   );
               }
           }

           return array_map([$this, 'validateValue'], $value);
       }

       return $value;
   }
   ```

---

### 3. Information Disclosure (Medium)

**Threat**: Snapshots expose sensitive data (passwords, API keys, PII).

**Attack Vector**:
```php
// Snapshot accidentally contains secrets
{
    "context": {
        "db_password": "supersecret123",
        "stripe_api_key": "sk_live_...",
        "user_ssn": "123-45-6789"
    }
}
```

**Risk Level**: 🟡 **MEDIUM**

**Mitigations**:

1. **Mandatory exclusion of sensitive keys**
   ```php
   class SecureSerializationPolicy extends SerializationPolicy
   {
       private const SENSITIVE_PATTERNS = [
           '/password/i',
           '/secret/i',
           '/api[_-]?key/i',
           '/token/i',
           '/ssn/i',
           '/credit[_-]?card/i',
       ];

       public function shouldExclude(string $key): bool
       {
           if (parent::shouldExclude($key)) {
               return true;
           }

           foreach (self::SENSITIVE_PATTERNS as $pattern) {
               if (preg_match($pattern, $key)) {
                   trigger_error(
                       "Automatically excluding sensitive key: {$key}",
                       E_USER_NOTICE
                   );
                   return true;
               }
           }

           return false;
       }
   }
   ```

2. **Encrypt snapshots at rest**
   ```php
   class EncryptedBackend implements PersistenceBackend
   {
       public function __construct(
           private PersistenceBackend $inner,
           private string $encryptionKey
       ) {}

       public function serialize(array $snapshot): string
       {
           $json = $this->inner->serialize($snapshot);
           $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

           $encrypted = sodium_crypto_secretbox(
               $json,
               $nonce,
               $this->encryptionKey
           );

           return base64_encode($nonce . $encrypted);
       }

       public function deserialize(string $data): array
       {
           $decoded = base64_decode($data);
           $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
           $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

           $json = sodium_crypto_secretbox_open(
               $ciphertext,
               $nonce,
               $this->encryptionKey
           );

           if ($json === false) {
               throw new SecurityException('Snapshot decryption failed');
           }

           return $this->inner->deserialize($json);
       }
   }
   ```

3. **Audit snapshot contents**
   ```php
   class AuditedPersistenceManager extends PersistenceManager
   {
       public function capture(Runtime $runtime): string
       {
           $snapshot = parent::capture($runtime);

           // Log snapshot creation with metadata
           $this->auditLog->record([
               'action' => 'snapshot_created',
               'timestamp' => time(),
               'user' => $this->currentUser->id,
               'size' => strlen($snapshot),
               'region_state' => $runtime->getRegion()->getCurrentState(),
               'contains_sensitive' => $this->detectSensitiveData($snapshot)
           ]);

           return $snapshot;
       }
   }
   ```

---

### 4. Denial of Service (Medium)

**Threat**: Malicious snapshots cause excessive memory/CPU usage during deserialization.

**Attack Vector**:
```php
// Billion laughs attack (XML bomb equivalent)
{
    "context": {
        "data": ["x", "x", "x", ...] // Millions of elements
    }
}
```

**Risk Level**: 🟡 **MEDIUM**

**Mitigations**:

1. **Size limits**
   ```php
   class SizeLimitedBackend implements PersistenceBackend
   {
       private const MAX_SNAPSHOT_SIZE = 10 * 1024 * 1024; // 10MB

       public function deserialize(string $data): array
       {
           if (strlen($data) > self::MAX_SNAPSHOT_SIZE) {
               throw new SecurityException(
                   'Snapshot exceeds maximum size limit'
               );
           }

           return json_decode($data, true, depth: 512, flags: JSON_THROW_ON_ERROR);
       }
   }
   ```

2. **Depth limits**
   ```php
   private function validateDepth(mixed $data, int $currentDepth = 0, int $maxDepth = 100): void
   {
       if ($currentDepth > $maxDepth) {
           throw new SecurityException('Snapshot depth exceeds limit');
       }

       if (is_array($data)) {
           foreach ($data as $value) {
               $this->validateDepth($value, $currentDepth + 1, $maxDepth);
           }
       }
   }
   ```

3. **Timeout protection**
   ```php
   public function restore(string $data): Runtime
   {
       $startTime = microtime(true);
       $maxTime = 5.0; // 5 seconds

       try {
           $snapshot = $this->backend->deserialize($data);

           if (microtime(true) - $startTime > $maxTime) {
               throw new SecurityException('Deserialization timeout');
           }

           return $this->restoreFromSnapshot($snapshot);
       } catch (\Throwable $e) {
           throw new SecurityException(
               'Restore failed: ' . $e->getMessage(),
               previous: $e
           );
       }
   }
   ```

4. **Memory limits**
   ```php
   class MemoryMonitoredBackend implements PersistenceBackend
   {
       public function deserialize(string $data): array
       {
           $startMemory = memory_get_usage();
           $maxIncrease = 100 * 1024 * 1024; // 100MB

           $result = json_decode($data, true);

           $memoryUsed = memory_get_usage() - $startMemory;
           if ($memoryUsed > $maxIncrease) {
               throw new SecurityException(
                   "Snapshot consumed {$memoryUsed} bytes of memory"
               );
           }

           return $result;
       }
   }
   ```

---

### 5. Path Traversal (Low)

**Threat**: File-based backends vulnerable to path manipulation.

**Attack Vector**:
```php
// Attacker controls snapshot ID
$snapshotId = "../../../etc/passwd";
$snapshot = file_get_contents("/var/snapshots/{$snapshotId}");
```

**Risk Level**: 🟢 **LOW** (if properly validated)

**Mitigations**:

1. **Validate snapshot IDs**
   ```php
   class FilesystemBackend implements PersistenceBackend
   {
       public function load(string $id): string
       {
           // Only allow alphanumeric + hyphens
           if (!preg_match('/^[a-zA-Z0-9\-]+$/', $id)) {
               throw new SecurityException('Invalid snapshot ID');
           }

           $path = $this->basePath . '/' . $id . '.snapshot';

           // Verify path is within base directory
           $realPath = realpath($path);
           $realBase = realpath($this->basePath);

           if ($realPath === false || !str_starts_with($realPath, $realBase)) {
               throw new SecurityException('Path traversal detected');
           }

           return file_get_contents($realPath);
       }
   }
   ```

2. **Use UUIDs for filenames**
   ```php
   public function save(string $snapshot): string
   {
       $id = Uuid::v4()->toString(); // Safe filename
       $path = $this->basePath . '/' . $id . '.snapshot';
       file_put_contents($path, $snapshot);
       return $id;
   }
   ```

---

## Security Best Practices

### 1. Defense in Depth

**Layer multiple security controls**:

```php
class SecurePersistenceFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Layer 1: Secure policy
        $policy = (new SecureSerializationPolicy())
            ->exclude('password', 'api_key', 'secret', 'token')
            ->skipClosures(true);

        // Layer 2: Encrypted backend
        $jsonBackend = new JsonBackend();
        $encryptedBackend = new EncryptedBackend($jsonBackend, $encryptionKey);

        // Layer 3: Signed backend
        $signedBackend = new SignedBackend($encryptedBackend, $signingSecret);

        // Layer 4: Size-limited backend
        $backend = new SizeLimitedBackend($signedBackend);

        // Layer 5: Audited manager
        $manager = new AuditedPersistenceManager($backend, $policy, $chainMail);

        $chainMail->supply(fn() => $manager);
    }
}
```

### 2. Principle of Least Privilege

**Store only what's necessary**:

```php
// ❌ Over-sharing
$this->set('user', $fullUserObject); // Contains password hash, email, etc.

// ✅ Minimal
$this->set('user_id', $userId); // Just the ID
```

### 3. Regular Security Audits

**Automated checks**:

```php
class SecurityAuditor
{
    public function auditSnapshot(string $snapshot): array
    {
        $data = json_decode($snapshot, true);
        $issues = [];

        // Check for sensitive keys
        $this->scanForSensitiveKeys($data, '', $issues);

        // Check for large values
        $this->scanForLargeValues($data, '', $issues);

        // Check for suspicious types
        $this->scanForSuspiciousTypes($data, '', $issues);

        return $issues;
    }

    private function scanForSensitiveKeys(mixed $data, string $path, array &$issues): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : $key;

                if ($this->isSensitiveKey($key)) {
                    $issues[] = [
                        'type' => 'sensitive_key',
                        'path' => $currentPath,
                        'severity' => 'high'
                    ];
                }

                $this->scanForSensitiveKeys($value, $currentPath, $issues);
            }
        }
    }
}
```

### 4. Secure Defaults

**Fail-safe configuration**:

```php
class SerializationPolicy
{
    // Secure by default
    private bool $skipClosures = true;
    private bool $excludeSensitive = true;
    private array $excludedKeys = [
        'password',
        'secret',
        'api_key',
        'token',
        'private_key'
    ];
}
```

### 5. Logging and Monitoring

**Track snapshot operations**:

```php
class MonitoredPersistenceManager extends PersistenceManager
{
    public function capture(Runtime $runtime): string
    {
        $startTime = microtime(true);
        $snapshot = parent::capture($runtime);
        $duration = microtime(true) - $startTime;

        $this->logger->info('Snapshot created', [
            'size' => strlen($snapshot),
            'duration' => $duration,
            'state' => $runtime->getRegion()->getCurrentState(),
            'user' => $this->getCurrentUser()?->id
        ]);

        // Alert on anomalies
        if (strlen($snapshot) > 5 * 1024 * 1024) { // > 5MB
            $this->logger->warning('Large snapshot detected', [
                'size' => strlen($snapshot)
            ]);
        }

        if ($duration > 1.0) { // > 1 second
            $this->logger->warning('Slow snapshot detected', [
                'duration' => $duration
            ]);
        }

        return $snapshot;
    }

    public function restore(string $data): Runtime
    {
        $this->logger->info('Snapshot restore started', [
            'size' => strlen($data),
            'user' => $this->getCurrentUser()?->id
        ]);

        try {
            $runtime = parent::restore($data);

            $this->logger->info('Snapshot restore succeeded', [
                'state' => $runtime->getRegion()->getCurrentState()
            ]);

            return $runtime;
        } catch (\Throwable $e) {
            $this->logger->error('Snapshot restore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
```

---

## Compliance Considerations

### GDPR / Data Privacy

**Snapshot snapshots may contain PII**:

1. **Right to be forgotten**
   ```php
   // Delete user's snapshots
   public function deleteUserSnapshots(string $userId): void
   {
       $snapshots = $this->findSnapshotsByUser($userId);

       foreach ($snapshots as $snapshotId) {
           $this->deleteSnapshot($snapshotId);

           $this->auditLog->record([
               'action' => 'snapshot_deleted',
               'user_id' => $userId,
               'snapshot_id' => $snapshotId,
               'reason' => 'gdpr_right_to_be_forgotten'
           ]);
       }
   }
   ```

2. **Data minimization**
   ```php
   // Only serialize necessary PII
   $policy->exclude('email', 'phone', 'address');
   $policy->registerSerializer(
       User::class,
       serialize: fn(User $u) => ['id' => $u->id], // ID only
       deserialize: fn(array $d) => User::find($d['id']) // Fetch from DB
   );
   ```

3. **Data retention**
   ```php
   // Auto-delete old snapshots
   $db->execute(
       "DELETE FROM snapshots WHERE created_at < :cutoff",
       ['cutoff' => time() - (30 * 86400)] // 30 days
   );
   ```

### SOC 2 / Security Controls

**Implement audit trail**:

```php
class ComplianceAuditLog
{
    public function recordAccess(string $action, array $context): void
    {
        $this->log([
            'timestamp' => microtime(true),
            'action' => $action,
            'user_id' => $context['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'snapshot_id' => $context['snapshot_id'] ?? null,
            'result' => $context['result'] ?? 'success'
        ]);
    }
}
```

---

## Security Checklist

Before deploying persistence to production:

- [ ] Encryption enabled for snapshots at rest
- [ ] Snapshot signatures validated on restore
- [ ] Class whitelist enforced
- [ ] Sensitive keys excluded by policy
- [ ] Size limits configured
- [ ] Depth limits configured
- [ ] Timeout protection enabled
- [ ] Access control implemented
- [ ] Audit logging enabled
- [ ] Anomaly detection configured
- [ ] GDPR compliance reviewed
- [ ] Penetration testing completed
- [ ] Security review by team
- [ ] Incident response plan documented

---

## Security Incident Response

### Compromised Snapshot Detected

1. **Immediate actions**:
   - Revoke access to affected snapshots
   - Disable snapshot restore temporarily
   - Alert security team

2. **Investigation**:
   - Identify scope of compromise
   - Review audit logs
   - Determine attack vector

3. **Remediation**:
   - Rotate encryption keys
   - Delete compromised snapshots
   - Patch vulnerability
   - Update security controls

4. **Recovery**:
   - Re-enable snapshot functionality
   - Monitor for recurrence
   - Update incident response plan

---

**Last Updated**: 2025-12-28
**Next Review**: Before Phase 3 implementation
**Owner**: Security Team + Engineering
