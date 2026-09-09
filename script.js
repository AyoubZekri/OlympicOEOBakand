const fs = require('fs');
let c = fs.readFileSync('D:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/EvaluationDialog.tsx', 'utf8');

c = c.replace(/const \[period, setPeriod\] = useState\([^)]+\);/, "const [period, setPeriod] = useState(initialData?.period || 'مرحلة الذهاب');");

c = c.replace(/<CustomDropdown\s*label="[^"]+"\s*options=\[\s*\{\s*value:\s*'first_half'[^\]]+\]\s*value=\{period\}\s*onChange=\{setPeriod\}\s*\/>/s, <CustomDropdown
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
                )});

c = c.replace(/<CustomInput type="date" [^\/]+\/>/g, '');

fs.writeFileSync('D:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/EvaluationDialog.tsx', c, 'utf8');
console.log('Modified successfully.');
