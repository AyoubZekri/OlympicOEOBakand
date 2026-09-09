import re

controller_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/MembersController.ts'
members_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Members.tsx'
eval_history_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/PlayerEvaluationsHistory.tsx'

# 1. Update MembersController.ts
with open(controller_path, 'r', encoding='utf-8') as f:
    c = f.read()

# remove addTestEvaluation method
c = re.sub(r'const addTestEvaluation = \(memberId: number\) => \{\s*const testEval = evalsData\.generateTestEvaluation\(memberId\);\s*saveEvaluation\(testEval\);\s*\};\s*', '', c)
c = c.replace('    addTestEvaluation,\n', '')

with open(controller_path, 'w', encoding='utf-8') as f:
    f.write(c)

print("MembersController.ts updated.")

# 2. Update Members.tsx
with open(members_path, 'r', encoding='utf-8') as f:
    m = f.read()

m = re.sub(r'\s*onAddTest=\{[^\}]+\}', '', m)

with open(members_path, 'w', encoding='utf-8') as f:
    f.write(m)
    
print("Members.tsx updated.")

# 3. Update PlayerEvaluationsHistory.tsx
try:
    with open(eval_history_path, 'r', encoding='utf-8') as f:
        h = f.read()

    h = h.replace('onAddTest?: () => void;', '')
    h = h.replace('onAddTest,', '')
    h = re.sub(r'<button className="eval-history-add-test-btn" onClick=\{onAddTest\}>.*?</button>', '', h, flags=re.DOTALL)
    
    with open(eval_history_path, 'w', encoding='utf-8') as f:
        f.write(h)
    print("PlayerEvaluationsHistory.tsx updated.")
except Exception as e:
    print(f"Error updating history: {e}")
