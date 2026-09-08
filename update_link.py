import os
import sys

file_path = r"d:\MyProject\KaidNews\OlympicOEO\src\LinkApi.ts"

try:
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()

    if "export const DISCIPLINARY =" not in content:
        content = content.replace(
            "export const EQUIPMENTS =",
            "export const DISCIPLINARY = `${BASE_URL}/disciplinary`;\nexport const CORRESPONDENCES = `${BASE_URL}/correspondences`;\nexport const EQUIPMENTS ="
        )

    with open(file_path, "w", encoding="utf-8") as f:
        f.write(content)
        
    print("Successfully updated LinkApi.ts")
except Exception as e:
    print("Error:", e)
