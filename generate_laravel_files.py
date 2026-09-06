import re
import os
from datetime import datetime, timedelta

dbml = """
Table equipments {
  id bigint [primary key]
  name varchar
  quantity integer
  added_by int
  updated_at timestamp
}

Table equipment_operations {
  id bigint [primary key]
  member_id int
  operation_date timestamp
  added_by int
}

Table equipment_movements {
  id bigint [primary key]
  operation_id bigint
  equipment_id bigint
  quantity integer
  movement_status varchar
  delivery_date timestamp
  delivery_condition varchar
  return_date timestamp
  return_condition varchar
}

Table correspondences {
  id bigint [primary key]
  sender_id int
  receiver_id int
  receiver_role varchar
  message_type varchar
  subject varchar
  content text
  required_action text
  is_acknowledged boolean
  acknowledged_at timestamp
  created_at timestamp
}

Table disciplinary_cases {
  id bigint [primary key]
  individuals_id int
  added_by int
  incident_date timestamp
  incident_location varchar
  violated_rule varchar
  description text
  present_people text
  attachments varchar
  case_status varchar
  created_at timestamp
}

Table disciplinary_actions {
  id bigint [primary key]
  case_id bigint
  action_type varchar 
  added_by int
  action_date timestamp
  deadline_or_hearing_date timestamp
  hearing_location varchar
  player_statements text
  admin_notes text
  decision_outcome varchar
  decision_reasons text
  effective_date timestamp
  is_acknowledged boolean
  acknowledged_at timestamp
}

Table hearing_attendees {
  id bigint [primary key]
  action_id bigint
  individuals_id int
  individuals_role varchar
}

Table player_evaluations {
  id bigint [primary key]
  player_id int
  coach_id int
  sporting_director_id int
  season varchar
  evaluation_type varchar
  period_start date
  period_end date
  matches_played int
  minutes_played int
  score_discipline int
  score_fitness int
  score_technical int
  score_tactical int
  score_match_performance int
  score_instructions int
  score_behavior int
  total_score int
  strengths text
  weaknesses text
  recommendation varchar
  created_at timestamp
}

Table improvement_programs {
  id bigint [primary key]
  evaluation_id bigint
  player_id int 
  program_start date
  program_end date
  areas_to_improve text
  specific_goals text
  actions_required text
  next_evaluation_date date
  is_acknowledged boolean
  created_at timestamp
}

Table contract_reviews {
  id bigint [primary key]
  player_id int 
  evaluation_id bigint
  club_representative_id int
  meeting_date timestamp
  discussed_topics text
  club_proposal text
  player_position text
  outcome varchar
  requires_official_avenant boolean
  player_signature boolean
  created_at timestamp
}

Table training_sessions {
  id bigint [primary key]
  session_date date
  shift varchar
  location varchar
  start_time time 
  end_time time
  supervisor_id int
  created_at timestamp
}

Table app_absences {
  id bigint [primary key]
  player_id int
  absence_type varchar
  event_category varchar
  event_date date
  training_session_id bigint
  record_source varchar
  duration varchar
  reason text
  attachment_path varchar
  is_justified boolean
  justification_status varchar
  decision_by int
  decision_date timestamp
  created_at timestamp
}

Table matches {
  id bigint [primary key]
  competition varchar
  opponent varchar
  match_title varchar
  match_date timestamp
  location varchar
  gathering_time timestamp
  gathering_location varchar
  coach_id int
  admin_id int
  created_at timestamp
}

Table match_callups {
  id bigint [primary key]
  match_id bigint
  player_id int
  notes varchar
  created_at timestamp
}

Table travel_itineraries {
  id bigint [primary key]
  match_id bigint
  destination varchar
  travel_reason varchar
  departure_location varchar
  departure_time timestamp
  transport_method varchar
  accommodation_place varchar
  return_time timestamp
  head_of_delegation_id int
  staff_details text
  players_count int
  schedule_departure time 
  schedule_arrival time 
  schedule_meal time 
  schedule_tech_meeting time 
  schedule_match time 
  schedule_return time 
  special_notes text
  created_at timestamp
}

Table administrative_match_reports {
  id bigint [primary key]
  match_id bigint
  admin_id int
  travel_as_planned boolean
  attendance_status varchar
  equipment_status varchar
  equipment_notes text
  accommodation_catering_notes text
  organizational_incidents text
  disciplinary_incidents text
  refereeing_notes text
  required_actions text
  report_date timestamp
}

Table match_bonuses {
  id bigint [primary key]
  match_id bigint
  match_result varchar
  bonus_type varchar
  base_amount decimal
  status varchar
  prepared_by int
  reviewed_by int
  approved_by int
  created_at timestamp
  updated_at timestamp
}

Table player_medical_records {
  id bigint [primary key]
  player_id int
  doctor_id int
  injury_date date
  incident_location varchar
  injury_nature varchar
  diagnosis text
  initial_recommendation varchar
  last_exam_date date
  medical_decision varchar
  restrictions text
  next_exam_date date
  record_status varchar
  created_at timestamp
  updated_at timestamp
}

Table player_clearances {
  id bigint [primary key]
  player_id int
  exit_date date
  exit_reason varchar
  equipment_status varchar
  equipment_notes text
  equipment_manager_id int
  equipment_cleared_at timestamp
  admin_status varchar
  admin_id int
  admin_cleared_at timestamp
  sporting_status varchar
  sporting_director_id int
  sporting_cleared_at timestamp
  financial_status varchar
  finance_manager_id int
  finance_cleared_at timestamp
  medical_status varchar
  medical_staff_id int
  medical_cleared_at timestamp
  general_notes text
  player_signature boolean
  player_signed_at timestamp
  created_at timestamp
}

Table player_activity_logs {
  id bigint [primary key]
  player_id int 
  event_date timestamp
  document_type varchar
  document_ref varchar
  subject varchar
  action_taken text
  result_outcome text
  created_at timestamp
}

Table department_meetings {
  id bigint [primary key]
  meeting_date timestamp
  agenda text
  created_by int
  created_at timestamp
}

Table meeting_attendees {
  id bigint [primary key]
  meeting_id bigint
  member_id int
}

Table meeting_decisions {
  id bigint [primary key]
  meeting_id bigint
  decision_text text
  assigned_to int
  deadline date
  execution_status varchar
  execution_notes text
  completed_at timestamp
}
"""

tables = []
current_table = None
for line in dbml.split('\n'):
    line = line.strip()
    if line.startswith('Table '):
        name = line.split(' ')[1]
        current_table = {'name': name, 'columns': []}
        tables.append(current_table)
    elif line.startswith('}') and current_table:
        current_table = None
    elif current_table and line and not line.startswith('//'):
        parts = line.split(' ')
        if len(parts) >= 2:
            col_name = parts[0]
            col_type = parts[1]
            current_table['columns'].append({'name': col_name, 'type': col_type})

def snake_to_camel(snake_str, capitalize_first=False):
    components = snake_str.split('_')
    camel = components[0] + ''.join(x.title() for x in components[1:])
    if capitalize_first:
        return camel[0].upper() + camel[1:]
    return camel

def singularize(word):
    if word.endswith('ies'): return word[:-3] + 'y'
    if word.endswith('s') and not word.endswith('ss'): return word[:-1]
    return word

laravel_type_map = {
    'bigint': 'bigInteger',
    'int': 'integer',
    'integer': 'integer',
    'varchar': 'string',
    'text': 'text',
    'boolean': 'boolean',
    'timestamp': 'timestamp',
    'date': 'date',
    'time': 'time',
    'decimal': 'decimal'
}

base_dir = r'd:\MyProject\KaidNews\OlympicOEOBakand'
models_dir = os.path.join(base_dir, 'app', 'Models')
migrations_dir = os.path.join(base_dir, 'database', 'migrations')

os.makedirs(models_dir, exist_ok=True)
os.makedirs(migrations_dir, exist_ok=True)

base_time = datetime.now()

for idx, table in enumerate(tables):
    table_name = table['name']
    model_name = snake_to_camel(singularize(table_name), True)
    if model_name == 'Matche': model_name = 'Match'
    if model_name == 'AppAbsence': model_name = 'AppAbsence'
    if model_name == 'EquipmentMovement': model_name = 'EquipmentMovement'
    if table_name == 'correspondences': model_name = 'Correspondence'
    
    # 1. Generate Migration File
    mig_code = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\n"
    mig_code += "return new class extends Migration\n{\n"
    mig_code += "    public function up()\n    {\n"
    mig_code += f"        Schema::create('{table_name}', function (Blueprint $table) {{\n"
    
    relations = []
    has_timestamps = False
    
    for col in table['columns']:
        col_name = col['name']
        col_type = col['type'].lower()
        laravel_type = laravel_type_map.get(col_type, 'string')
        
        if col_name == 'id':
            mig_code += f"            $table->id();\n"
        elif col_name in ['created_at', 'updated_at']:
            has_timestamps = True
        else:
            nullable = "->nullable()"
            if col_name in ['added_by', 'admin_id']:
                mig_code += f"            $table->foreignId('{col_name}'){nullable}->constrained('users');\n"
                relations.append((col_name, 'User', snake_to_camel(col_name)))
            elif col_name in ['member_id', 'doctor_id', 'player_id', 'individuals_id', 'coach_id', 'supervisor_id', 'sporting_director_id', 'sender_id', 'receiver_id', 'club_representative_id', 'decision_by', 'prepared_by', 'reviewed_by', 'approved_by', 'equipment_manager_id', 'finance_manager_id', 'medical_staff_id', 'created_by', 'assigned_to', 'head_of_delegation_id']:
                mig_code += f"            $table->foreignId('{col_name}'){nullable}->constrained('individuals');\n"
                relations.append((col_name, 'Individual', snake_to_camel(col_name)))
            elif col_name.endswith('_id'):
                related_table = col_name[:-3] + 's'
                if related_table == 'matchs': related_table = 'matches'
                if related_table == 'equipment_operations': related_table = 'equipment_operations'
                
                mig_code += f"            $table->foreignId('{col_name}'){nullable}->constrained('{related_table}');\n"
                rel_model = snake_to_camel(singularize(related_table), True)
                if rel_model == 'Matche': rel_model = 'Match'
                relations.append((col_name, rel_model, snake_to_camel(col_name)))
            else:
                mig_code += f"            $table->{laravel_type}('{col_name}'){nullable};\n"
                
    if has_timestamps:
        mig_code += "            $table->timestamps();\n"
    
    mig_code += "        });\n    }\n\n"
    mig_code += "    public function down()\n    {\n"
    mig_code += f"        Schema::dropIfExists('{table_name}');\n"
    mig_code += "    }\n};\n"
    
    timestamp_str = (base_time + timedelta(seconds=idx)).strftime('%Y_%m_%d_%H%M%S')
    mig_filename = f"{timestamp_str}_create_{table_name}_table.php"
    mig_path = os.path.join(migrations_dir, mig_filename)
    
    with open(mig_path, 'w', encoding='utf-8') as f:
        f.write(mig_code)
        
    # 2. Generate Model File
    mod_code = "<?php\n\nnamespace App\\Models;\n\n"
    mod_code += "use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\n"
    mod_code += "use Illuminate\\Database\\Eloquent\\Model;\n\n"
    mod_code += f"class {model_name} extends Model\n{{\n"
    mod_code += "    use HasFactory;\n\n"
    mod_code += f"    protected $table = '{table_name}';\n"
    mod_code += f"    protected $guarded = ['id'];\n"
    
    if not has_timestamps:
        mod_code += f"\n    public $timestamps = false;\n"
        
    for fk, rel_model, method_name in relations:
        mod_code += f"\n    public function {method_name}()\n    {{\n"
        mod_code += f"        return $this->belongsTo({rel_model}::class, '{fk}');\n"
        mod_code += f"    }}\n"
        
    mod_code += "}\n"
    
    mod_path = os.path.join(models_dir, f"{model_name}.php")
    with open(mod_path, 'w', encoding='utf-8') as f:
        f.write(mod_code)

print("Files generated successfully in Models and migrations directories.")
