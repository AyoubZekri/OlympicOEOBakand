import re

controller_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/MembersController.ts'

with open(controller_path, 'r', encoding='utf-8') as f:
    c = f.read()

# Replace getEvaluationsByMember
c = c.replace('const data = evalsData.getEvaluationsByMember(memberId);', 'const data = await evalsData.getEvaluationsByMember(memberId);')

# Replace saveEvaluation
c = c.replace('evalsData.saveEvaluation(evaluation);', 'await evalsData.saveEvaluation(evaluation);')

# Replace updateEvaluation
c = c.replace('evalsData.updateEvaluation(evaluation);', 'await evalsData.updateEvaluation(evaluation);')

# Replace deleteEvaluation
c = c.replace('evalsData.deleteEvaluation(id);', 'await evalsData.deleteEvaluation(id);')

# The loadEvaluations function should be async
c = c.replace('const loadEvaluations = (memberId: number) => {', 'const loadEvaluations = async (memberId: number) => {')

with open(controller_path, 'w', encoding='utf-8') as f:
    f.write(c)

print("MembersController.ts updated.")
