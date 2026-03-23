import os
import re

def revert_file(filepath, log_func_name):
    if not os.path.exists(filepath): return
    with open(filepath, 'r') as f:
        content = f.read()

    # Remove the helper log method
    pattern_method = r"    private function " + log_func_name + r"\(.*?\)\s*\{\s*\\Illuminate\\Support\\Facades\\Log::error.*?\s*\\App\\Models\\Global\\GlobalLog::create\(\[.*?\]\);\s*session\(\)->flash\('error_message'.*?\);\s*\}"
    content = re.sub(pattern_method, "", content, flags=re.DOTALL)
    
    # Remove validation wrapper variant 1 (Livewire directly passthrough):
    pattern_val1 = r"        try \{\s*(.*?)\s*\} catch \(\\Illuminate\\Validation\\ValidationException \$e\) \{\s*throw \$e;.*?\s*\} catch \(\\Exception \$e\) \{\s*\$this->" + log_func_name + r"\(.*?\);\s*\}"
    content = re.sub(pattern_val1, r"        \1", content, flags=re.DOTALL)
    
    # Remove normal wrapper
    pattern_norm = r"        try \{\s*(.*?)\s*\} catch \(\\Exception \$e\) \{\s*\$this->" + log_func_name + r"\(.*?\);\s*\}"
    content = re.sub(pattern_norm, r"        \1", content, flags=re.DOTALL)

    # Some indentations might need fixing ideally, but re.sub preserves inner indentation.
    # To fix the shifting we caused:
    # Actually wait. `pattern_val1` and `pattern_norm` capture lines that have 4 extra spaces. 
    # To fix indentation:
    def fix_indent(match):
        inner = match.group(1)
        # remove 4 spaces from the start of each line in `inner`
        lines = inner.split('\n')
        fixed_lines = []
        for line in lines:
            if line.startswith('    '):
                fixed_lines.append(line[4:])
            else:
                fixed_lines.append(line)
        return '\n'.join(fixed_lines)

    # Re-do with indent fix:
    with open(filepath, 'r') as f:
        content = f.read()
    content = re.sub(pattern_method, "", content, flags=re.DOTALL)
    
    pattern_val1_group = r"        try \{\n(.*?)        \} catch \(\\Illuminate\\Validation\\ValidationException \$e\)"
    # A bit risky to regex the try block perfectly across multi-lines if of nested structure.
    # A better approach: since the original try was perfectly added 4 spaces right, we can just replace the try/catch bookends.
    
    with open(filepath, 'w') as f:
        f.write(content)

# We will use simple replace for the blade views
def remove_blade_error(filepath, error_block):
    if not os.path.exists(filepath): return
    with open(filepath, 'r') as f:
        content = f.read()
    content = content.replace(error_block, "")
    with open(filepath, 'w') as f:
        f.write(content)

# It's much safer to replace block by block or use git checkout if the files only had my changes.
