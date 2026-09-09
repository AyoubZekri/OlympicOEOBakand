import re

file_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/EvaluationDialog.tsx'
with open(file_path, 'r', encoding='utf-8') as f:
    c = f.read()

# Replace the initial state
c = re.sub(
    r"const \[period, setPeriod\] = useState\([^)]+\);",
    "const [period, setPeriod] = useState(initialData?.period || 'مرحلة الذهاب');",
    c
)

# Replace the meta grid block
start_str = '<div className="eval-meta-grid">'
end_str = '</div>\n            </div>\n\n            <div className="eval-sliders-container">'

if start_str in c and end_str in c:
    start_idx = c.find(start_str) + len(start_str)
    end_idx = c.find(end_str)
    
    new_grid = '''
                <CustomDropdown
                  label="الموسم الرياضي"
                  options={[{ value: '2024-2025', label: '2024-2025' }, { value: '2023-2024', label: '2023-2024' }]}
                  value={season}
                  onChange={setSeason}
                />
                <CustomDropdown
                  label="نوع التقييم"
                  options={[
                    { value: 'مرحلة الذهاب', label: 'مرحلة الذهاب' },
                    { value: 'مرحلة الإياب', label: 'مرحلة الإياب' },
                    { value: 'شهري', label: 'شهري' },
                    { value: 'نهاية الموسم', label: 'نهاية الموسم' }
                  ]}
                  value={period}
                  onChange={setPeriod}
                />
                {period === 'شهري' && (
                  <CustomInput type="month" label="الشهر المعني" value={fromDate ? fromDate.substring(0, 7) : ''} onChange={(e: any) => { setFromDate(e.target.value + '-01'); setToDate(e.target.value + '-28'); }} className="eval-input" />
                )}
              '''
    
    c = c[:start_idx] + new_grid + c[end_idx:]

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(c)

print('Done completely!')
