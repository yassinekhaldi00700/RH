from datetime import datetime, timedelta, date
import pandas as pd
import json
import sys

# No schedule barriers - 24-hour tracking system
# All users work in an open schedule

USER_WORKDAYS = {
    # Define which days users work (0=Monday, 6=Sunday)
    # Default is Monday-Friday [0,1,2,3,4] if not specified
}

PUBLIC_HOLIDAYS = {
    "2025-05-01": "Jour ferie",
    "2025-06-27": "Jour ferie",
    "2025-07-30": "Jour ferie",
    "2025-08-14": "Jour ferie",
    "2025-08-20": "Jour ferie",
    "2025-08-21": "Jour ferie",
    "2025-09-05": "Jour ferie",
    "2025-11-06": "Jour ferie",
    "2025-11-18": "Jour ferie",
}

def get_public_holiday_name(date_str):
    return PUBLIC_HOLIDAYS.get(date_str, "Jour ferie")

# Lists of device IDs for entries and exits
ENTRY_DEVICES = ['546271448', '546271459', '546271451', '546271441', '546271439', '546271457', 'PUNCH_TYPE_BREAK_END']
EXIT_DEVICES = ['546271450', '546271456', '546271446', '546271465', '546271455', '546271449', 'PUNCH_TYPE_BREAK_START']

def convert_minutes_to_hms(minutes):
    """Convert minutes to HH:MM:SS format"""
    if minutes is None or minutes <= 0:
        return "00:00:00"
    
    seconds = int(minutes * 60)
    hours, remainder = divmod(seconds, 3600)
    minutes, seconds = divmod(remainder, 60)
    return f"{hours:02}:{minutes:02}:{seconds:02}"

def calculate_total_time_worked(entries, exits):
    """Calculate total time worked from all entry/exit pairs in minutes"""
    if not entries or not exits:
        return 0
    
    total_minutes = 0
    
    # Match each entry with its corresponding exit
    for i in range(min(len(entries), len(exits))):
        if entries[i] and exits[i]:
            # Calculate time between entry and exit
            entry_dt = datetime.combine(datetime.today(), entries[i])
            exit_dt = datetime.combine(datetime.today(), exits[i])
            
            # Handle case where exit is after midnight
            if exit_dt < entry_dt:
                exit_dt += timedelta(days=1)
            
            duration = (exit_dt - entry_dt).total_seconds() / 60
            total_minutes += duration
    
    return total_minutes

def filter_close_times(times, threshold_seconds=100):
    """Filter out times that are too close together (within threshold)"""
    if not times:
        return []
    
    filtered = [times[0]]
    for time in times[1:]:
        last_time = filtered[-1]
        delta = abs((datetime.combine(datetime.today(), time) -
                    datetime.combine(datetime.today(), last_time)).total_seconds())
        
        if delta > threshold_seconds:
            filtered.append(time)
    
    return filtered

def midnight_sort_key(t):
    """Sort key that treats 00:00–01:59 times as occurring AFTER 02:00+ times.
    Needed because shifts can cross midnight: a punch at 00:06 is actually
    later in the shift than a punch at 19:41 or 23:58."""
    if t.hour < 2:
        return (t.hour + 24, t.minute, t.second)
    return (t.hour, t.minute, t.second)

def get_date_range(start_date, end_date):
    """Generate list of dates between start and end"""
    date_list = []
    current_date = start_date
    while current_date <= end_date:
        date_list.append(current_date)
        current_date += timedelta(days=1)
    return date_list

def is_weekend(date_obj, user_id):
    """Check if date is a weekend for the user"""
    allowed_days = USER_WORKDAYS.get(str(user_id), [0, 1, 2, 3, 4])
    return date_obj.weekday() not in allowed_days

def process_attendance_data(data):
    """Main function to process attendance data"""
    # Support new format: {"records": [...], "all_employees": [...], "start_date": "...", "end_date": "..."}
    global PUBLIC_HOLIDAYS
    master_employees = []
    filter_start_date = None
    filter_end_date = None
    if isinstance(data, dict) and 'records' in data:
        master_employees = data.get('all_employees', [])
        filter_start_date = data.get('start_date')
        filter_end_date = data.get('end_date')
        if isinstance(data.get('public_holidays'), dict):
            PUBLIC_HOLIDAYS.update({str(k): v for k, v in data.get('public_holidays', {}).items()})
        data = data['records']

    df = pd.DataFrame(data) if data else pd.DataFrame(columns=['srvdt', 'devuid', 'usrid', 'user_name', 'group_name', 'record_type'])
    df['srvdt'] = pd.to_datetime(df['srvdt'], format='%Y-%m-%d %H:%M:%S', errors='coerce')
    # df['srvdt'] = df['srvdt'] - pd.Timedelta(hours=1)  # Subtract 1 hour from all timestamps
    df['Date'] = df['srvdt'].dt.date
    df['Time'] = df['srvdt'].dt.time

    # Handle midnight crossover: punches between 00:00 and 01:59 AM belong to the previous day's shift
    midnight_mask = df['Time'].apply(lambda t: t.hour < 2)
    df.loc[midnight_mask, 'Date'] = (df.loc[midnight_mask, 'srvdt'] - pd.Timedelta(days=1)).dt.date
    
    df = df.rename(columns={
        'devuid': 'DevUID',
        'usrid': 'UserID',
        'user_name': 'Name',
        'group_name': 'Department',
    })
    
    if 'record_type' not in df.columns:
        df['record_type'] = None
    
    df['Type'] = df['DevUID'].apply(lambda x: 'In' if x in ENTRY_DEVICES else 'Out' if x in EXIT_DEVICES else 'Unknown')
    df = df[df['Type'] != 'Unknown']
    df['Date'] = pd.to_datetime(df['Date'])
   
    all_employees = df[['Name', 'UserID', 'Department']].drop_duplicates().to_dict('records') if not df.empty else []
    min_date = df['Date'].min() if not df.empty else pd.NaT
    max_date = df['Date'].max() if not df.empty else pd.NaT
    # Use filter dates as fallback when there are no punch records in the period
    if pd.isna(min_date) and filter_start_date:
        min_date = pd.to_datetime(filter_start_date)
    if pd.isna(max_date) and filter_end_date:
        max_date = pd.to_datetime(filter_end_date)
    if pd.isna(min_date) or pd.isna(max_date):
        return json.dumps({"processed_data": [], "summary_data": []})
    all_workdays = get_date_range(min_date, max_date)
   
    processed_data = []
    grouped_data = df.groupby(['Date', 'Name', 'UserID', 'Department'])
    attendance_record = {employee['UserID']: set() for employee in all_employees}

    # Add zero-punch employees from master list (appear in the group but never punched)
    punched_ids = {emp['UserID'] for emp in all_employees}
    for emp in master_employees:
        uid = str(emp.get('user_id', ''))
        if uid and uid not in punched_ids:
            all_employees.append({'UserID': uid, 'Name': emp.get('user_name', ''), 'Department': emp.get('department', '')})
            attendance_record[uid] = set()
            punched_ids.add(uid)

    for (date, name, user_id, department), group in grouped_data:
        date_only = date.date() if hasattr(date, 'date') else date
        date_str = date_only.strftime('%Y-%m-%d')
        
        # Check for public holiday first
        if date_str in PUBLIC_HOLIDAYS:
            exception = get_public_holiday_name(date_str)
            status = "Absent"
            record = {
                'Date': date.strftime('%Y-%m-%d'),
                'Nom': name,
                'ID utilisateur': user_id,
                'Département': department,
                'Status': status,
                'Exception': exception,
                'Premier Entree': None,
                'Sortie Final': None,
                'Retard 1 ': "00:00:00",
                'Sortie en avance 1 ': "00:00:00",
                'Retard 2 ': "00:00:00",
                'Sortie en avance 2 ': "00:00:00",
                'la somme Sortie en avance': "00:00:00",
                'la somme Retard': "00:00:00",
                'la somme du Retards General': "00:00:00",
                'Heure Supp': "00:00:00",
            }
            
            for i in range(1, 5):
                record[f'e{i}'] = None
                record[f's{i}'] = None
            processed_data.append(record)
            attendance_record[user_id].add(date)
            continue  

        attendance_record[user_id].add(date)
        
        record_types = group['record_type'].unique() if 'record_type' in group.columns else []
        special_record_types = [rt for rt in record_types 
                              if rt not in ["Original", "Modified"] 
                              and rt is not None 
                              and pd.notna(rt)]
        special_record_type = special_record_types[0] if special_record_types else None
        
        in_times = group[group['Type'] == 'In']['Time'].tolist()
        out_times = group[group['Type'] == 'Out']['Time'].tolist()
        
        in_times.sort(key=midnight_sort_key)
        out_times.sort(key=midnight_sort_key)
        status = "present"
        
        in_times_filtered = filter_close_times(in_times)
        out_times_filtered = filter_close_times(out_times)
        
        entries = {}
        exits = {}
        
        for i, time in enumerate(in_times_filtered[:4], 1):
            entries[f'e{i}'] = time
        
        for i, time in enumerate(out_times_filtered[:4], 1):
            exits[f's{i}'] = time
        
        for i in range(1, 5):
            if f'e{i}' not in entries:
                entries[f'e{i}'] = None
            if f's{i}' not in exits:
                exits[f's{i}'] = None
        
        # Check for anomalies
        num_entries = sum(1 for v in entries.values() if v)
        num_exits = sum(1 for v in exits.values() if v)
        exception = ""
        
        if (num_entries + num_exits) % 2 == 1 or (num_entries > 0 and num_exits == 0) or (num_exits > 0 and num_entries == 0):
            status = "anomalie"
            exception = "Nombre d'entrées et de sorties incohérent"
        elif any(
            entries[f'e{i}'] and exits[f's{i}']
            and midnight_sort_key(entries[f'e{i}']) > midnight_sort_key(exits[f's{i}'])
            for i in range(1, 5)
        ):
            status = "anomalie"
            exception = "Valeur de sortie inferieure a la valeur d'entree"

        premier_entrie = entries['e1']
        
        sortie_final = None
        for i in range(4, 0, -1):
            if exits[f's{i}']:
                sortie_final = exits[f's{i}']
                break
        
        is_weekend_day = is_weekend(date, user_id)
        
        # No schedule barriers - just track time worked
        if special_record_type:
            # Handle special record types (leave, absence, etc.)
            status = "partir"
            exception = special_record_type
            # Clear entries/exits for auto-generated leaves
            if not premier_entrie or not sortie_final:
                for i in range(1, 5):
                    entries[f'e{i}'] = None
                    exits[f's{i}'] = None
                premier_entrie = None
                sortie_final = None
                temps_travaille = 0
            else:
                # Calculate actual time worked if present
                all_entries = [entries[f'e{i}'] for i in range(1, 5) if entries[f'e{i}']]
                all_exits = [exits[f's{i}'] for i in range(1, 5) if exits[f's{i}']]
                temps_travaille = calculate_total_time_worked(all_entries, all_exits)
        
        elif is_weekend_day:
            # Even on weekends, if there's pointage (attendance), calculate time worked
            status = "Pas de travail"
            exception = ""
            if status == "anomalie":
                temps_travaille = 0
            else:
                # Calculate time worked if they actually came in
                all_entries = [entries[f'e{i}'] for i in range(1, 5) if entries[f'e{i}']]
                all_exits = [exits[f's{i}'] for i in range(1, 5) if exits[f's{i}']]
                temps_travaille = calculate_total_time_worked(all_entries, all_exits)
        
        else:
            if status == "anomalie":
                temps_travaille = 0
            else:
                # Calculate total time worked from all entry/exit pairs
                all_entries = [entries[f'e{i}'] for i in range(1, 5) if entries[f'e{i}']]
                all_exits = [exits[f's{i}'] for i in range(1, 5) if exits[f's{i}']]
                temps_travaille = calculate_total_time_worked(all_entries, all_exits)

        record = {
            'Date': date.strftime('%Y-%m-%d'),
            'Nom': name,
            'ID utilisateur': user_id,
            'Département': department,
            'Status': status,
            'Exception': exception,
            'Premier Entree': premier_entrie.strftime('%H:%M:%S') if premier_entrie else None,
            'Sortie Final': sortie_final.strftime('%H:%M:%S') if sortie_final else None,
            'Retard 1 ': "00:00:00",
            'Sortie en avance 1 ': "00:00:00",
            'Retard 2 ': "00:00:00",
            'Sortie en avance 2 ': "00:00:00",
            'la somme Sortie en avance': "00:00:00",
            'la somme Retard': "00:00:00",
            'la somme du Retards General': convert_minutes_to_hms(temps_travaille),
            'Heure Supp': "00:00:00",
        }
       
        for i in range(1, 5):
            record[f'e{i}'] = entries[f'e{i}'].strftime('%H:%M:%S') if entries[f'e{i}'] else None
            record[f's{i}'] = exits[f's{i}'].strftime('%H:%M:%S') if exits[f's{i}'] else None
       
        processed_data.append(record)

    # Handle absences
    for employee in all_employees:
        user_id = employee['UserID']
        name = employee['Name']
        department = employee['Department']
       
        for workday in all_workdays:
            workday_str = workday.strftime('%Y-%m-%d')
            
            if workday not in attendance_record[user_id]:
                # Check if it's a public holiday first
                if workday_str in PUBLIC_HOLIDAYS:
                    status = "Absent"
                    exception = get_public_holiday_name(workday_str)
                else:
                    is_weekend_day = is_weekend(workday, user_id)
                    if is_weekend_day:
                        status = "Pas de travail"
                        exception = ""
                    else:
                        status = "Absent"
                        exception = ""
                
                absence_record = {
                    'Date': workday.strftime('%Y-%m-%d'),
                    'Nom': name,
                    'ID utilisateur': user_id,
                    'Département': department,
                    'Status': status,
                    'Exception': exception,
                    'Premier Entree': None,
                    'Sortie Final': None,
                    'Retard 1 ': "00:00:00",
                    'Sortie en avance 1 ': "00:00:00",
                    'Retard 2 ': "00:00:00",
                    'Sortie en avance 2 ': "00:00:00",
                    'la somme Sortie en avance': "00:00:00",
                    'la somme Retard': "00:00:00",
                    'la somme du Retards General': "00:00:00",
                    'Heure Supp': "00:00:00",
                }
               
                for i in range(1, 5):
                    absence_record[f'e{i}'] = None
                    absence_record[f's{i}'] = None
               
                processed_data.append(absence_record)

    result_df = pd.DataFrame(processed_data)
    result_df = result_df.sort_values(by=['Date', 'Nom', 'ID utilisateur', 'Département'])
    
    summary_data = generate_summary_report(result_df)
    
    processed_records = result_df.to_dict('records')
    summary_records = summary_data.to_dict('records')
    
    return {
        "processed_data": processed_records,
        "summary_data": summary_records
    }              

def generate_summary_report(df):
    """Generate cumulative summary for each employee"""
    def time_to_minutes(time_str):
        if pd.isna(time_str) or time_str is None or time_str == "00:00:00":
            return 0
        try:
            parts = time_str.split(':')
            return int(parts[0]) * 60 + int(parts[1]) + int(parts[2])/60
        except:
            return 0

    summary_data = []
    for (name, user_id, department), group in df.groupby(['Nom', 'ID utilisateur', 'Département']):
        # Include all records except purely absent ones (include "Pas de travail" if they have pointage)
        all_work_records = group[group['Status'] != "Absent"]
        
        total_temps_travaille = sum(time_to_minutes(x) for x in all_work_records['la somme du Retards General'])
        
        absent_days = len(group[
            (group['Status'] == 'Absent') &
            (~group['Date'].astype(str).isin(PUBLIC_HOLIDAYS.keys()))
        ])
       
        try:
            min_date = min(pd.to_datetime(group['Date']))
            max_date = max(pd.to_datetime(group['Date']))
            period = f"{min_date.strftime('%Y-%m-%d')} to {max_date.strftime('%Y-%m-%d')}"
        except:
            period = "N/A"
       
        summary_data.append({
            'Nom': name,
            'ID utilisateur': user_id,
            'Département': department,
            'Period': period,
            'Total Jours Absents': absent_days,
            'Total Sortie en avance': "00:00:00",
            'Total Retard': "00:00:00",
            'Total Retard General': convert_minutes_to_hms(total_temps_travaille),
            'Total Heures Supplementaires': "00:00:00"
        })
   
    return pd.DataFrame(summary_data)

def process_attendance(data_json=None):
    """Process attendance from JSON input"""
    try:
        if not data_json:
            data_json = sys.stdin.read()
        
        data = json.loads(data_json)
        result = process_attendance_data(data)
        return json.dumps(result)
    except Exception as e:
        return json.dumps({"error": f"Python Error: {str(e)}"})

if __name__ == "__main__":
    try:
        if len(sys.argv) > 1:
            with open(sys.argv[1], 'r', encoding='utf-8', errors='replace') as f:
                data_json = f.read()
        else:
            if sys.stdin.isatty():
                print(json.dumps({"error": "No input provided"}))
                sys.exit(1)
            data_json = sys.stdin.read()

        result_json = process_attendance(data_json)
        print(result_json)
    except Exception as e:
        print(json.dumps({"error": str(e)}))
