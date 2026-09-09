const fs = require('fs');
let text = fs.readFileSync('D:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/EvaluationDialog.tsx', 'utf8');
try {
  let fixed = Buffer.from(text, 'latin1').toString('utf8');
  console.log('Fixed snippet:', fixed.substring(1000, 1100));
} catch(e) { console.log(e); }
