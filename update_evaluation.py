import re

linkapi_path = 'd:/MyProject/KaidNews/OlympicOEO/src/LinkApi.ts'
eval_data_path = 'd:/MyProject/KaidNews/OlympicOEO/src/View/Screen/Members/Evaluation/evaluation_data.ts'

# Update LinkApi.ts
with open(linkapi_path, 'r', encoding='utf-8') as f:
    link_content = f.read()

if 'player-evaluations' not in link_content:
    new_endpoints = """//  =============================Player Evaluations============================== //

  static readonly playerEvaluations: string = `${Applink.server}/player-evaluations`;
  static readonly createPlayerEvaluation: string = `${Applink.server}/player-evaluations/create`;
  static readonly updatePlayerEvaluation: string = `${Applink.server}/player-evaluations/update`;
  static readonly deletePlayerEvaluation: string = `${Applink.server}/player-evaluations/delete`;

}"""
    link_content = link_content.replace('}\n', new_endpoints + '\n')
    with open(linkapi_path, 'w', encoding='utf-8') as f:
        f.write(link_content)
    print("LinkApi.ts updated.")

# Update evaluation_data.ts
new_eval_data = """import axios from 'axios';
import { Applink } from '../../../../LinkApi';

export interface EvaluationRecord {
  id: string;
  member_id: number;
  season: string;
  period: string;
  evalDate: string;
  totalScore: number;
  recommendation: string;
  strengths: string;
  weaknesses: string;
  // Specific scores
  scores: {
    discipline: number;
    physical: number;
    technical: number;
    tactical: number;
    matchOutput: number;
    instructions: number;
    behavior: number;
  };
}

export class EvaluationsData {
  
  // Load all evaluations for a specific member
  public async getEvaluationsByMember(memberId: number): Promise<EvaluationRecord[]> {
    try {
      const response = await axios.get(`${Applink.playerEvaluations}?member_id=${memberId}`, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
        }
      });
      if (response.data && response.data.data) {
        // Map the backend data to EvaluationRecord
        return response.data.data.map((item: any) => ({
          id: item.id.toString(),
          member_id: item.member_id,
          season: item.sports_season || '',
          period: item.evaluation_type || '',
          evalDate: item.evaluation_date || '',
          totalScore: item.total_score || 0,
          recommendation: item.recommendations || '',
          strengths: item.strengths || '',
          weaknesses: item.weaknesses || '',
          scores: {
            discipline: item.discipline_score || 0,
            physical: item.physical_score || 0,
            technical: item.technical_score || 0,
            tactical: item.tactical_score || 0,
            matchOutput: item.match_output_score || 0,
            instructions: item.instructions_score || 0,
            behavior: item.behavior_score || 0,
          }
        }));
      }
      return [];
    } catch (e) {
      console.error("Failed to load evaluations from API", e);
      return [];
    }
  }

  // Save a new evaluation
  public async saveEvaluation(evaluation: Omit<EvaluationRecord, 'id'>): Promise<EvaluationRecord | null> {
    try {
      const payload = {
        member_id: evaluation.member_id,
        evaluation_date: evaluation.evalDate,
        evaluation_type: evaluation.period,
        sports_season: evaluation.season,
        physical_score: evaluation.scores.physical,
        technical_score: evaluation.scores.technical,
        tactical_score: evaluation.scores.tactical,
        match_output_score: evaluation.scores.matchOutput,
        discipline_score: evaluation.scores.discipline,
        instructions_score: evaluation.scores.instructions,
        behavior_score: evaluation.scores.behavior,
        total_score: evaluation.totalScore,
        strengths: evaluation.strengths,
        weaknesses: evaluation.weaknesses,
        recommendations: evaluation.recommendation,
      };

      const response = await axios.post(Applink.createPlayerEvaluation, payload, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
        }
      });
      
      if (response.data && response.data.data) {
        const item = response.data.data;
        return {
          id: item.id.toString(),
          member_id: item.member_id,
          season: item.sports_season || '',
          period: item.evaluation_type || '',
          evalDate: item.evaluation_date || '',
          totalScore: item.total_score || 0,
          recommendation: item.recommendations || '',
          strengths: item.strengths || '',
          weaknesses: item.weaknesses || '',
          scores: {
            discipline: item.discipline_score || 0,
            physical: item.physical_score || 0,
            technical: item.technical_score || 0,
            tactical: item.tactical_score || 0,
            matchOutput: item.match_output_score || 0,
            instructions: item.instructions_score || 0,
            behavior: item.behavior_score || 0,
          }
        };
      }
      return null;
    } catch (e) {
      console.error("Failed to save evaluation to API", e);
      throw e;
    }
  }

  // Update an existing evaluation
  public async updateEvaluation(evaluation: EvaluationRecord): Promise<EvaluationRecord | null> {
    try {
      const payload = {
        id: evaluation.id,
        member_id: evaluation.member_id,
        evaluation_date: evaluation.evalDate,
        evaluation_type: evaluation.period,
        sports_season: evaluation.season,
        physical_score: evaluation.scores.physical,
        technical_score: evaluation.scores.technical,
        tactical_score: evaluation.scores.tactical,
        match_output_score: evaluation.scores.matchOutput,
        discipline_score: evaluation.scores.discipline,
        instructions_score: evaluation.scores.instructions,
        behavior_score: evaluation.scores.behavior,
        total_score: evaluation.totalScore,
        strengths: evaluation.strengths,
        weaknesses: evaluation.weaknesses,
        recommendations: evaluation.recommendation,
      };

      const response = await axios.post(Applink.updatePlayerEvaluation, payload, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
        }
      });
      
      if (response.data && response.data.data) {
        return evaluation;
      }
      return null;
    } catch (e) {
      console.error("Failed to update evaluation", e);
      throw e;
    }
  }

  // Delete an evaluation
  public async deleteEvaluation(id: string): Promise<boolean> {
    try {
      const response = await axios.post(Applink.deletePlayerEvaluation, { id: id }, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
        }
      });
      return response.status === 200;
    } catch (e) {
      console.error("Failed to delete evaluation", e);
      throw e;
    }
  }
}
"""

with open(eval_data_path, 'w', encoding='utf-8') as f:
    f.write(new_eval_data)
print("evaluation_data.ts updated.")
