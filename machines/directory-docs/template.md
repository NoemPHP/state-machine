#   {{#complete max=48}}
            # Code
            {{allFiles}}

            # Instruction
            Write a concise headline for the readme file of the folder containing the code above.
            Do not use any formatting, return plaintext only. 10 words max.

            # Output
{{/complete}}

{{#complete max=128}}
    {{systemPrompt}}
            # Code
            {{allFiles}}
            # Instruction

            Write a concise first paragraph outlining the purpose behind the code contained within
            the current directory. Focus on the main purpose and avoid unnecessary details.
            Consider how all code relates to one another.

{{/complete}}

# Use cases

{{#complete max=256}}
    {{systemPrompt}}

            # Code

            {{allFiles}}
            # Instruction

            Write a list of example usage scenarios.
            Do not write code examples, just high-level descriptions of problems
            that can be solved with this code.

{{/complete}}



# Design & Architecture

{{#complete max=256}}
    {{systemPrompt}}

        # Code

        {{allFiles}}
        # Instruction
        Write an introduction to this section, followed by a description of
        the code patterns and architectural considerations found in the code.
        If required, outline potential future improvements

{{/complete}}

# Example

{{#complete max=512}}
    {{systemPrompt}}

            # Code

            {{allFiles}}
            # Instruction

            Write an introduction to this section, followed by a very short concise
            code example outlining how the code can be used in practice.
            If possible, try to make it relevant
            to one or several of the usage scenarios outlines already

{{/complete}}
