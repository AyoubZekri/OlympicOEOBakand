import os
import codecs

def remove_bom(file_path):
    with open(file_path, 'rb') as f:
        content = f.read()
    if content.startswith(codecs.BOM_UTF8):
        print(f"BOM found and removed in {file_path}")
        with open(file_path, 'wb') as f:
            f.write(content[3:])

remove_bom('app/Http/Controllers/Api/MatchController.php')
remove_bom('database/migrations/2026_09_14_125804_add_score_and_status_to_matches_table.php')
