import os

files = [
    'app/Http/Controllers/Api/MatchController.php',
    'app/Models/Matchs.php',
    'database/migrations/2026_09_13_131903_add_team_id_to_matches_table.php'
]

for file_path in files:
    try:
        with open(file_path, 'r', encoding='utf-8-sig') as f:
            content = f.read()
            
        content = content.lstrip() # Remove any leading whitespace or newlines just in case
        
        with open(file_path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(content)
        print(f"Fixed {file_path}")
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
