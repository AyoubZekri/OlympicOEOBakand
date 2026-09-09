import sys
import re

file_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/EvaluationDialog.tsx'
with open(file_path, 'r', encoding='utf-8') as f:
    c = f.read()

c = re.sub(
    r"const \[period, setPeriod\] = useState\(initialData\?\.period \|\| 'first_half'\);",
    "const [period, setPeriod] = useState(initialData?.period || 'مرحلة الذهاب');",
    c
)

c = re.sub(
    r'<CustomDropdown\s*label="فترة التقييم"\s*options=\[\s*\{\s*value:\s*\'first_half\',\s*label:\s*\'نصف الموسم الأول\'\s*},\s*\{\s*value:\s*\'second_half\',\s*label:\s*\'نصف الموسم الثاني\'\s*\}\s*\]\s*value=\{period\}\s*onChange=\{setPeriod\}\s*\/>',
    '''<CustomDropdown
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
                )}''',
    c,
    flags=re.DOTALL
)

c = re.sub(
    r'<CustomInput type="date" label="من تاريخ" value=\{fromDate\} onChange=\{\(e\) => setFromDate\(e\.target\.value\)\} className="eval-input" \/>',
    '',
    c
)

c = re.sub(
    r'<CustomInput type="date" label="إلى تاريخ" value=\{toDate\} onChange=\{\(e\) => setToDate\(e\.target\.value\)\} className="eval-input" \/>',
    '',
    c
)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(c)

print('Done')
