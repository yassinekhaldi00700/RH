import pandas as pd
from datetime import datetime, timedelta, date
import json
import sys
import mysql.connector
from mysql.connector import Error

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'users'
}

# Global dictionaries that will be populated from database
DEFAULT_SCHEDULES = {}
DEFAULT_SCHEDULES_RAMADAN = {}
USER_SCHEDULES = {}
USER_SCHEDULES_RAMADAN = {}
USER_SATURDAY_SCHEDULES = {}
USER_SATURDAY_SCHEDULES_RAMADAN = {}
USER_WORKDAYS = {}
USER_WORKDAYS_RAMADAN = {}
PUBLIC_HOLIDAYS = set()
RAMADAN_PERIODS = []

def get_db_connection():
    """Create and return a database connection"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        return connection
    except Error as e:
        print(f"Error connecting to MySQL: {e}", file=sys.stderr)
        return None

def load_ramadan_periods_from_db():
    """Load Ramadan periods from the database"""
    global RAMADAN_PERIODS
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor()
        cursor.execute("SELECT ramadan_start, ramadan_end FROM ramadan ORDER BY ramadan_start")
        periods = cursor.fetchall()
        
        RAMADAN_PERIODS = [(period[0], period[1]) for period in periods]
        cursor.close()
    except Error as e:
        print(f"Error loading Ramadan periods: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def is_ramadan_date(date_obj):
    """Check if a given date falls within any Ramadan period"""
    if not date_obj:
        return False
    
    check_date = date_obj.date() if hasattr(date_obj, 'date') else date_obj
    
    for ramadan_start, ramadan_end in RAMADAN_PERIODS:
        if ramadan_start <= check_date <= ramadan_end:
            return True
    return False

def load_schedules_from_db():
    """Load all schedules from the database (both normal and Ramadan)"""
    global DEFAULT_SCHEDULES, DEFAULT_SCHEDULES_RAMADAN
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT * FROM schedules")
        schedules = cursor.fetchall()
        
        for schedule in schedules:
            schedule_data = {
                "MORNING_START": str(schedule['morning_start']),
                "LUNCH_START": str(schedule['lunch_start']),
                "LUNCH_END": str(schedule['lunch_end']),
                "WORKDAY_END": str(schedule['workday_end']),
                "MORNING_THRESHOLD": str(schedule['morning_threshold']),
                "LUNCH_THRESHOLD": str(schedule['lunch_threshold']),
                "AFTERNOON_THRESHOLD": str(schedule['afternoon_threshold']),
                "EVENING_THRESHOLD": str(schedule['evening_threshold']),
                "MORNING_PENALTY": schedule['morning_penalty'],
                "LUNCH_PENALTY": schedule['lunch_penalty'],
                "AFTERNOON_PENALTY": schedule['afternoon_penalty']
            }
            
            # Handle ramadan flag (can be int, str, or bool)
            ramadan_flag = schedule.get('ramadan', 0)
            is_ramadan = (str(ramadan_flag) == '1' or ramadan_flag == 1 or ramadan_flag is True)
            
            if is_ramadan:
                DEFAULT_SCHEDULES_RAMADAN[schedule['name']] = schedule_data
            else:
                DEFAULT_SCHEDULES[schedule['name']] = schedule_data
        
        cursor.close()
    except Error as e:
        print(f"Error loading schedules: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def load_user_schedules_from_db():
    """Load user schedule assignments from the database (both normal and Ramadan)"""
    global USER_SCHEDULES, USER_SCHEDULES_RAMADAN
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor(dictionary=True)
        query = """
            SELECT us.employee_code, s.name as schedule_name, us.ramadan
            FROM users_schedules us
            LEFT JOIN schedules s ON us.schedule_id = s.id
            WHERE us.schedule_id IS NOT NULL
        """
        cursor.execute(query)
        user_schedules = cursor.fetchall()
        
        for user_schedule in user_schedules:
            employee_code = str(user_schedule['employee_code'])
            schedule_name = user_schedule['schedule_name']
            
            # Handle ramadan flag (can be int, str, or bool)
            ramadan_flag = user_schedule.get('ramadan', 0)
            is_ramadan = (str(ramadan_flag) == '1' or ramadan_flag == 1 or ramadan_flag is True)
            
            if is_ramadan:
                USER_SCHEDULES_RAMADAN[employee_code] = schedule_name
            else:
                USER_SCHEDULES[employee_code] = schedule_name
        
        cursor.close()
    except Error as e:
        print(f"Error loading user schedules: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def load_saturday_schedules_from_db():
    """Load Saturday special schedules from the database (both normal and Ramadan)"""
    global USER_SATURDAY_SCHEDULES, USER_SATURDAY_SCHEDULES_RAMADAN
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor(dictionary=True)
        query = """
            SELECT us.employee_code, s.name as schedule_name, us.ramadan
            FROM saturday_special_schedules sss
            JOIN users_schedules us ON sss.user_id = us.id
            JOIN schedules s ON sss.schedule_id = s.id
        """
        cursor.execute(query)
        saturday_schedules = cursor.fetchall()
        
        for sat_schedule in saturday_schedules:
            employee_code = str(sat_schedule['employee_code'])
            schedule_name = sat_schedule['schedule_name']
            
            # Handle ramadan flag (can be int, str, or bool)
            ramadan_flag = sat_schedule.get('ramadan', 0)
            is_ramadan = (str(ramadan_flag) == '1' or ramadan_flag == 1 or ramadan_flag is True)
            
            if is_ramadan:
                USER_SATURDAY_SCHEDULES_RAMADAN[employee_code] = schedule_name
            else:
                USER_SATURDAY_SCHEDULES[employee_code] = schedule_name
        
        cursor.close()
    except Error as e:
        print(f"Error loading Saturday schedules: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def load_user_workdays_from_db():
    """Load user workdays from the database (both normal and Ramadan)"""
    global USER_WORKDAYS, USER_WORKDAYS_RAMADAN
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor(dictionary=True)
        query = """
            SELECT us.employee_code, uw.weekday, us.ramadan
            FROM user_workdays uw
            JOIN users_schedules us ON uw.user_id = us.id
            ORDER BY us.employee_code, uw.weekday
        """
        cursor.execute(query)
        workdays = cursor.fetchall()
        
        for workday in workdays:
            employee_code = str(workday['employee_code'])
            weekday_num = workday['weekday']
            
            if workday.get('ramadan') == 1:
                if employee_code not in USER_WORKDAYS_RAMADAN:
                    USER_WORKDAYS_RAMADAN[employee_code] = []
                USER_WORKDAYS_RAMADAN[employee_code].append(weekday_num)
            else:
                if employee_code not in USER_WORKDAYS:
                    USER_WORKDAYS[employee_code] = []
                USER_WORKDAYS[employee_code].append(weekday_num)
        
        cursor.close()
    except Error as e:
        print(f"Error loading user workdays: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def load_public_holidays_from_db():
    """Load public holidays from the database"""
    global PUBLIC_HOLIDAYS
    connection = get_db_connection()
    if not connection:
        return
    
    try:
        cursor = connection.cursor()
        cursor.execute("SELECT holiday_date FROM public_holidays")
        holidays = cursor.fetchall()
        
        for holiday in holidays:
            PUBLIC_HOLIDAYS.add(holiday[0].strftime('%Y-%m-%d'))
        
        cursor.close()
    except Error as e:
        print(f"Error loading public holidays: {e}", file=sys.stderr)
    finally:
        if connection.is_connected():
            connection.close()

def initialize_from_database():
    """Load all configuration from database"""
    load_ramadan_periods_from_db()
    load_schedules_from_db()
    load_user_schedules_from_db()
    load_saturday_schedules_from_db()
    load_user_workdays_from_db()
    load_public_holidays_from_db()

# Lists of device IDs for entries and exits
ENTRY_DEVICES = ['546271448', '546271459', '546271451', '546271441', '546271439', '546271457', 'PUNCH_TYPE_BREAK_END']
EXIT_DEVICES = ['546271450', '546271456', '546271446', '546271465', '546271455', '546271449', 'PUNCH_TYPE_BREAK_START']

def get_user_schedule(user_id, date_obj=None):
    user_id_str = str(user_id)
    
    # Check if date is in Ramadan period
    is_ramadan = is_ramadan_date(date_obj) if date_obj else False
    
    # Select appropriate data sources based on Ramadan status
    if is_ramadan:
        schedules_dict = DEFAULT_SCHEDULES_RAMADAN
        user_schedules_dict = USER_SCHEDULES_RAMADAN
        saturday_schedules_dict = USER_SATURDAY_SCHEDULES_RAMADAN
    else:
        schedules_dict = DEFAULT_SCHEDULES
        user_schedules_dict = USER_SCHEDULES
        saturday_schedules_dict = USER_SATURDAY_SCHEDULES
    
    # If it's Saturday and user has a special Saturday schedule
    if date_obj and date_obj.weekday() == 5:  # 5 = Saturday
        saturday_schedule = saturday_schedules_dict.get(user_id_str)
        if saturday_schedule and saturday_schedule in schedules_dict:
            return schedules_dict[saturday_schedule]
        # During Ramadan: fall back to normal Saturday schedule if no Ramadan-specific one
        if is_ramadan:
            normal_saturday_schedule = USER_SATURDAY_SCHEDULES.get(user_id_str)
            if normal_saturday_schedule and normal_saturday_schedule in DEFAULT_SCHEDULES:
                return DEFAULT_SCHEDULES[normal_saturday_schedule]
    
    # Get user's assigned schedule
    schedule_type = user_schedules_dict.get(user_id_str)
    
    # If user has assigned schedule and it exists, use it
    if schedule_type and schedule_type in schedules_dict:
        return schedules_dict[schedule_type]
    
    # During Ramadan: fall back to normal (non-Ramadan) schedule if no Ramadan-specific one configured
    if is_ramadan:
        normal_schedule_type = USER_SCHEDULES.get(user_id_str)
        if normal_schedule_type and normal_schedule_type in DEFAULT_SCHEDULES:
            return DEFAULT_SCHEDULES[normal_schedule_type]
    
    # Fallback: use first available schedule or return default
    if schedules_dict:
        return list(schedules_dict.values())[0]
    
    # Ultimate fallback: return a basic schedule
    return {
        "MORNING_START": "09:00:00",
        "LUNCH_START": "12:30:00",
        "LUNCH_END": "14:00:00",
        "WORKDAY_END": "18:00:00",
        "MORNING_THRESHOLD": "09:00:00",
        "LUNCH_THRESHOLD": "12:15:00",
        "AFTERNOON_THRESHOLD": "14:15:00",
        "EVENING_THRESHOLD": "17:45:00",
        "MORNING_PENALTY": 30,
        "LUNCH_PENALTY": 15,
        "AFTERNOON_PENALTY": 15
    }

def combine_time(base_date, t, next_day=False):
    """Combine a date with a time object. If next_day=True, adds 1 day (for times past midnight)."""
    dt = datetime.combine(base_date, t)
    if next_day:
        dt += timedelta(days=1)
    return dt

def time_to_dt(t, schedule):
    """
    Convert a time to a datetime using today as base date.
    If the schedule's WORKDAY_END is before MORNING_START (crosses midnight)
    and the time is before 04:00 AM, it is treated as belonging to the next day.
    """
    base = datetime.today().replace(hour=0, minute=0, second=0, microsecond=0)
    morning_start = datetime.strptime(schedule["MORNING_START"], "%H:%M:%S").time()
    workday_end = datetime.strptime(schedule["WORKDAY_END"], "%H:%M:%S").time()
    crosses_midnight = workday_end < morning_start
    if crosses_midnight and t.hour < 4:
        return datetime.combine(base.date(), t) + timedelta(days=1)
    return datetime.combine(base.date(), t)

def convert_minutes_to_hms(minutes):
    if minutes is None or minutes <= 0:
        return "00:00:00"
    
    seconds = int(minutes * 60)
    hours, remainder = divmod(seconds, 3600)
    minutes, seconds = divmod(remainder, 60)
    return f"{hours:02}:{minutes:02}:{seconds:02}"

def calculate_morning_delay(entry_time, schedule):
    if not entry_time:
        return 0
    
    morning_start = datetime.strptime(schedule["MORNING_START"], "%H:%M:%S").time()
    lunch_start = datetime.strptime(schedule["LUNCH_START"], "%H:%M:%S").time()
    lunch_end = datetime.strptime(schedule["AFTERNOON_THRESHOLD"], "%H:%M:%S").time()

    entry_dt     = time_to_dt(entry_time,  schedule)
    morning_dt   = time_to_dt(morning_start, schedule)
    lunch_s_dt   = time_to_dt(lunch_start,  schedule)
    lunch_e_dt   = time_to_dt(lunch_end,    schedule)

    if entry_dt >= lunch_s_dt and entry_dt < lunch_e_dt:
        delta = lunch_s_dt - morning_dt
        delay_minutes = delta.total_seconds() / 60
        return delay_minutes + schedule["MORNING_PENALTY"]
    
    if entry_dt >= lunch_e_dt:
        delta = (lunch_s_dt - morning_dt) + (entry_dt - lunch_e_dt)
        delay_minutes = delta.total_seconds() / 60
        return delay_minutes + schedule["MORNING_PENALTY"] + schedule["LUNCH_PENALTY"]
    
    if entry_dt <= morning_dt:
        return 0
    
    delta = entry_dt - morning_dt
    delay_minutes = delta.total_seconds() / 60
    
    return delay_minutes + schedule["MORNING_PENALTY"]

def calculate_lunch_early_departure(exit_time, schedule):
    if not exit_time:
        return 0
    
    threshold = datetime.strptime(schedule["LUNCH_THRESHOLD"], "%H:%M:%S").time()

    exit_dt      = time_to_dt(exit_time, schedule)
    threshold_dt = time_to_dt(threshold,  schedule)

    if exit_dt >= threshold_dt:
        return 0
    
    delta = threshold_dt - exit_dt
    early_minutes = delta.total_seconds() / 60
    
    return early_minutes + schedule["LUNCH_PENALTY"]

def calculate_afternoon_delay(entry_time, schedule):
    if not entry_time:
        return 0
    
    lunch_end   = datetime.strptime(schedule["LUNCH_END"],            "%H:%M:%S").time()
    threshold   = datetime.strptime(schedule["AFTERNOON_THRESHOLD"],  "%H:%M:%S").time()
    workday_end = datetime.strptime(schedule["WORKDAY_END"],          "%H:%M:%S").time()

    entry_dt      = time_to_dt(entry_time,  schedule)
    lunch_end_dt  = time_to_dt(lunch_end,   schedule)
    threshold_dt  = time_to_dt(threshold,   schedule)
    workday_dt    = time_to_dt(workday_end, schedule)

    if entry_dt < lunch_end_dt or entry_dt > workday_dt:
        return 0
    
    if entry_dt <= threshold_dt:
        return 0
    
    delta = entry_dt - threshold_dt
    delay_minutes = delta.total_seconds() / 60
    
    return delay_minutes + schedule["AFTERNOON_PENALTY"]

def calculate_evening_early_departure(exit_time, schedule):
    if not exit_time:
        return 0

    workday_end = datetime.strptime(schedule["WORKDAY_END"],      "%H:%M:%S").time()
    threshold   = datetime.strptime(schedule["EVENING_THRESHOLD"], "%H:%M:%S").time()
    lunch_start = datetime.strptime(schedule["LUNCH_START"],       "%H:%M:%S").time()
    lunch_end   = datetime.strptime(schedule["LUNCH_END"],         "%H:%M:%S").time()

    exit_dt       = time_to_dt(exit_time,   schedule)
    workday_dt    = time_to_dt(workday_end, schedule)
    threshold_dt  = time_to_dt(threshold,   schedule)
    lunch_s_dt    = time_to_dt(lunch_start, schedule)
    lunch_e_dt    = time_to_dt(lunch_end,   schedule)

    if exit_dt >= threshold_dt:
        return 0

    if lunch_s_dt <= exit_dt <= lunch_e_dt:
        delta = workday_dt - lunch_e_dt
        return delta.total_seconds() / 60

    if exit_dt < lunch_s_dt:
        delta = workday_dt - lunch_e_dt
        return delta.total_seconds() / 60

    delta = threshold_dt - exit_dt
    early_minutes = delta.total_seconds() / 60

    return early_minutes + schedule["AFTERNOON_PENALTY"]

def calculate_heure_supp(entries, exits, schedule):
    if not entries or not exits:
        return "00:00:00"
    
    workday_end = datetime.strptime(schedule["WORKDAY_END"], "%H:%M:%S").time()
    workday_dt  = time_to_dt(workday_end, schedule)
    total_supp_minutes = 0
    
    if entries[0] and exits[0]:
        exit_dt = time_to_dt(exits[0], schedule)
        if exit_dt > workday_dt:
            total_supp_minutes += (exit_dt - workday_dt).total_seconds() / 60
    
    for i in range(1, len(entries)):
        if entries[i]:
            entry_dt = time_to_dt(entries[i], schedule)
            if entry_dt > workday_dt:
                if i < len(exits) and exits[i]:
                    exit_dt = time_to_dt(exits[i], schedule)
                    total_supp_minutes += (exit_dt - entry_dt).total_seconds() / 60
            elif i < len(exits) and exits[i]:
                exit_dt = time_to_dt(exits[i], schedule)
                if exit_dt > workday_dt:
                    total_supp_minutes += (exit_dt - workday_dt).total_seconds() / 60
    
    return convert_minutes_to_hms(total_supp_minutes)

def filter_close_times(times, threshold_seconds=100):
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



def get_date_range(start_date, end_date):
    date_list = []
    current_date = start_date
    while current_date <= end_date:
        date_list.append(current_date)
        current_date += timedelta(days=1)
    return date_list

def is_weekend(date_obj, user_id):
    # Check if date is in Ramadan period
    is_ramadan = is_ramadan_date(date_obj)
    user_id_str = str(user_id)
    
    # Get allowed workdays for the user based on Ramadan status.
    # If no Ramadan-specific workdays are configured, fall back to normal workdays.
    if is_ramadan:
        allowed_days = (USER_WORKDAYS_RAMADAN.get(user_id_str)
                        or USER_WORKDAYS.get(user_id_str, [0, 1, 2, 3, 4]))
    else:
        allowed_days = USER_WORKDAYS.get(user_id_str, [0, 1, 2, 3, 4])
    
    return date_obj.weekday() not in allowed_days

def process_attendance_data(data):
    df = pd.DataFrame(data)
    df['srvdt'] = pd.to_datetime(df['srvdt'], format='%Y-%m-%d %H:%M:%S', errors='coerce')
    # Only subtract 1 hour for real biometric punches (Original/Modified/null record_type).
    # Manually entered 'partir' records from the DB already have correct local time.
    if 'record_type' in df.columns:
        is_real_punch = df['record_type'].isna() | df['record_type'].isin(['Original', 'Modified'])
        df.loc[is_real_punch, 'srvdt'] = df.loc[is_real_punch, 'srvdt'] - pd.Timedelta(hours=1)
    else:
        df['srvdt'] = df['srvdt'] - pd.Timedelta(hours=1)
    df['Date'] = df['srvdt'].dt.date
    df['Time'] = df['srvdt'].dt.time

    # Handle midnight crossover: punches between 00:00 and 04:00 AM belong to the previous day's shift
    midnight_mask = df['Time'].apply(lambda t: t.hour < 4)
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
   
    all_employees = df[['Name', 'UserID', 'Department']].drop_duplicates().to_dict('records')
    min_date = df['Date'].min()
    max_date = df['Date'].max()
    all_workdays = get_date_range(min_date, max_date)
   
    processed_data = []
    grouped_data = df.groupby(['Date', 'Name', 'UserID', 'Department'])
    attendance_record = {employee['UserID']: set() for employee in all_employees}

    for (date, name, user_id, department), group in grouped_data:
        date_only = date.date() if hasattr(date, 'date') else date
        date_str = date_only.strftime('%Y-%m-%d')
        
        # Check for public holiday first
        if date_str in PUBLIC_HOLIDAYS:
            exception = "Jour férié"
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
        
        in_times.sort()
        out_times.sort()
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
        
        # After building entries and exits dictionaries and before setting status
        num_entries = sum(1 for v in entries.values() if v)
        num_exits = sum(1 for v in exits.values() if v)
        if (num_entries + num_exits) % 2 == 1 or (num_entries > 0 and num_exits == 0) or (num_exits > 0 and num_entries == 0):
            status = "anomalie"
            exception = "Nombre d'entrées et de sorties incohérent"
        elif any(
            entries[f'e{i}'] and exits[f's{i}'] and entries[f'e{i}'] > exits[f's{i}']
            for i in range(1, 5)
        ):
            status = "anomalie"
            exception = "valeur de sortie inferieure a la valeur d'entree"
        else:
            status = "present"
            exception = ""


        premier_entrie = entries['e1']
        
        sortie_final = None
        for i in range(4, 0, -1):
            if exits[f's{i}']:
                sortie_final = exits[f's{i}']
                break
        
        is_weekend_day = is_weekend(date, user_id)
        
        # Get user-specific schedule
        user_schedule = get_user_schedule(user_id, date)
        
        if special_record_type:
            # Convert the times to strings for comparison
            premier_entry_str = premier_entrie.strftime('%H:%M:%S') if premier_entrie else None
            sortie_final_str = sortie_final.strftime('%H:%M:%S') if sortie_final else None

            morning_start = datetime.strptime(user_schedule["MORNING_START"], "%H:%M:%S").time()
            workday_end = datetime.strptime(user_schedule["WORKDAY_END"], "%H:%M:%S").time()

            # Calculate expected entry time (MORNING_START - 30 minutes)
            expected_entry_dt = (datetime.combine(datetime.today(), morning_start) - timedelta(minutes=30))
            expected_entry_time = expected_entry_dt.time()
            # Check if the entry and exit times match our target values
            if premier_entry_str == expected_entry_time.strftime('%H:%M:%S') and sortie_final_str == workday_end.strftime('%H:%M:%S'):
                # Your specific logic for this case
                premier_entrie = ""  # Or whatever you want to set it to
                sortie_final = ""
                entries['e1']=""
                exits['s1']=""
                entries['e2']=""
                exits['s2']=""
                entries['e3']=""
                exits['s3']=""
                entries['e4']=""
                exits['s4']=""
                status = "partir"
                exception = special_record_type
                diff_premier_entrie = 0
                diff_pause_sortie = 0
                diff_entrer_apres_pause = 0
                diff_sortie_final = 0
                sum_sortie_en_avance = 0
                sum_retard = 0
                sum_retard_general = 0
                heure_supp = "00:00:00"
            else:
                # Original logic for other special record types
                status = "partir"
                exception += ","+special_record_type
                diff_premier_entrie = calculate_morning_delay(premier_entrie, user_schedule) if premier_entrie else 0
                diff_pause_sortie = calculate_lunch_early_departure(exits['s1'], user_schedule) if exits['s1'] else 0
                diff_entrer_apres_pause = calculate_afternoon_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                diff_sortie_final = calculate_evening_early_departure(sortie_final, user_schedule) if sortie_final else 0
                diff_pause_sortie = min(diff_pause_sortie, 240)
                    
                # (mohasaratt) surround the 6th case with this if 
                if num_entries == 3 and num_exits == 3:
                # Get schedule thresholds
                        morning_start = datetime.strptime(user_schedule["MORNING_START"], "%H:%M:%S").time()
                        custom_morning_start = (datetime.combine(datetime.today(), morning_start) - timedelta(minutes=30)).time()
                        
                        lunch_threshold = datetime.strptime(user_schedule["LUNCH_THRESHOLD"], "%H:%M:%S").time()
                        afternoon_threshold = datetime.strptime(user_schedule["AFTERNOON_THRESHOLD"], "%H:%M:%S").time()
                        evening_threshold = datetime.strptime(user_schedule["EVENING_THRESHOLD"], "%H:%M:%S").time()

                        # s1 and s2 between 9:00 and 12:45
                        if exits['s1'] and entries['e2']:
                            if (custom_morning_start <= exits['s1'] <= lunch_threshold) and (custom_morning_start <= entries['e2'] <= lunch_threshold):
                                    # Add e2-s1 to SA1 (Sortie en avance 1)
                                    delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                    diff_pause_sortie = max(0, delta1)  
                            elif (exits['s1'] < custom_morning_start  ) and ( entries['e2'] < custom_morning_start):
                                    diff_pause_sortie = 0
                            elif(custom_morning_start > exits['s1']) and (custom_morning_start >= entries['e1'] ):
                                diff_premier_entrie = calculate_morning_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                                diff_pause_sortie = calculate_lunch_early_departure(exits['s2'], user_schedule) if exits['s2'] else 0
                                diff_entrer_apres_pause = calculate_afternoon_delay(entries['e3'], user_schedule) if entries['e3'] else 0
                                diff_sortie_final = calculate_evening_early_departure(exits['s3'], user_schedule) if exits['s3'] else 0
                                diff_pause_sortie = min(diff_pause_sortie, 240)
                            elif (afternoon_threshold <= exits['s1'] <= evening_threshold) and (afternoon_threshold <= entries['e2'] <= evening_threshold):
                                # Add e2-s1 to SA2 (Sortie en avance 1) if value null 0 at return
                                delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                diff_entrer_apres_pause = max(0, delta1)
                                        # in minute
                        # s2 and e3 between 14:30 and 17:45
                        if exits['s2'] and entries['e3']:
                            if (afternoon_threshold <= exits['s2'] <= evening_threshold) and (afternoon_threshold <= entries['e3'] <= evening_threshold):
                                # Add e3-s2 to SA2 (Sortie en avance 2)
                                delta2 = (datetime.combine(datetime.today(), entries['e3']) - datetime.combine(datetime.today(), exits['s2'])).total_seconds() / 60
                                diff_sortie_final += max(0, delta2)  # in minutes
                            elif ( exits['s2'] <= afternoon_threshold) and (afternoon_threshold <= entries['e3'] <= evening_threshold):
                                diff_entrer_apres_pause = calculate_afternoon_delay(entries['e3'], user_schedule) if entries['e3'] else 0
                            elif  (custom_morning_start <= exits['s2'] <= lunch_threshold) and (custom_morning_start <= entries['e3'] <= lunch_threshold):
                                delta2 = (datetime.combine(datetime.today(), entries['e3']) - datetime.combine(datetime.today(), exits['s2'])).total_seconds() / 60
                                diff_sortie_final += max(0, delta2)
                            elif (afternoon_threshold <= exits['s2'] <= evening_threshold):
                                workday_end_dt = datetime.combine(datetime.today(), datetime.strptime("18:00:00", "%H:%M:%S").time())
                                exits_s2_dt = datetime.combine(datetime.today(), exits['s2'])
                                delta2 = (workday_end_dt - exits_s2_dt).total_seconds() / 60
                                diff_sortie_final += max(0, delta2)
                            
                            
                if num_entries == 2 and num_exits == 2: 
                    # Get schedule thresholds
                    morning_start = datetime.strptime(user_schedule["MORNING_START"], "%H:%M:%S").time()
                    custom_morning_start = (datetime.combine(datetime.today(), morning_start) - timedelta(minutes=30)).time()
                    
                    lunch_threshold = datetime.strptime(user_schedule["LUNCH_THRESHOLD"], "%H:%M:%S").time()
                    afternoon_threshold = datetime.strptime(user_schedule["AFTERNOON_THRESHOLD"], "%H:%M:%S").time()
                    evening_threshold = datetime.strptime(user_schedule["WORKDAY_END"], "%H:%M:%S").time()

                    # s1 and s2 between 9:00 and 12:45
                    if exits['s1'] and entries['e2']:
                        if (custom_morning_start <= exits['s1'] <= lunch_threshold) and (custom_morning_start <= entries['e2'] <= lunch_threshold):
                                # Add e2-s1 to SA1 (Sortie en avance 1)
                                delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                diff_pause_sortie = max(0, delta1)  
                        elif (afternoon_threshold <= exits['s1'] <= evening_threshold) and (afternoon_threshold <= entries['e2'] <= evening_threshold):
                            # Add e3-s2 to SA2 (Sortie en avance 2)
                            delta2 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                            diff_entrer_apres_pause = max(0, delta2)
                        elif(custom_morning_start > exits['s1']) and (custom_morning_start >= entries['e1'] ):
                            diff_premier_entrie = calculate_morning_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                            diff_pause_sortie = 0
                            diff_entrer_apres_pause = 0
                            diff_sortie_final = calculate_evening_early_departure(exits['s2'], user_schedule) if exits['s2'] else 0
                        elif (exits['s2'] and exits['s2'] > evening_threshold) and (entries['e2'] and entries['e2'] > evening_threshold) and (exits['s1'] and afternoon_threshold <= exits['s1'] <= evening_threshold):
                            diff_sortie_final = calculate_evening_early_departure(exits['s1'], user_schedule)
                        elif (exits['s2'] and exits['s2'] > evening_threshold) and (entries['e2'] and entries['e2'] > evening_threshold) and (exits['s1'] and morning_start <= exits['s1'] <= afternoon_threshold):
                            diff_sortie_final = calculate_evening_early_departure(exits['s1'], user_schedule)

                                

                if num_entries == 1 and num_exits == 1:
                    # Get schedule thresholds
                    afternoon_threshold = datetime.strptime(user_schedule["LUNCH_END"], "%H:%M:%S").time()  # 14:00
                    
                    # Case: if e1 is after 14:00, calculate both R1(e1) and R2(e1)
                    if entries['e1'] and entries['e1'] > afternoon_threshold:
                        # R1(e1) - Calculate morning delay based on e1 (max 4 hours = 240 minutes)
                        diff_premier_entrie = min(calculate_morning_delay(entries['e1'], user_schedule), 240)
                        
                        # R2(e1) - Calculate afternoon delay based on e1
                        diff_entrer_apres_pause = calculate_afternoon_delay(entries['e1'], user_schedule)
                        
                        # Keep existing SA2 calculation
                        diff_sortie_final = calculate_evening_early_departure(sortie_final, user_schedule) if sortie_final else 0

                if diff_premier_entrie > 0:
                    exception += "Arriver en retard , "

                if diff_pause_sortie > 0:
                    exception += "Depart anticipe pour pause , "

                if diff_entrer_apres_pause > 0:
                    exception += "Retour en retard apres pause , "

                if diff_sortie_final > 0:
                    exception += "Depart anticipe en fin de journee , "

                exception = exception.strip()
                if exception.endswith(','):
                    exception = exception[:-1].strip()
            
                sum_sortie_en_avance = diff_pause_sortie + diff_sortie_final
                sum_retard = diff_premier_entrie + diff_entrer_apres_pause
                sum_retard_general = sum_retard + sum_sortie_en_avance
                if sum_retard_general < 0:
                    sum_retard_general = 0
            
                all_entries = [entries[f'e{i}'] for i in range(1, 5) if entries[f'e{i}']]
                all_exits = [exits[f's{i}'] for i in range(1, 5) if exits[f's{i}']]
                heure_supp = calculate_heure_supp(all_entries, all_exits, user_schedule)
            
        elif is_weekend_day:
            status = "Pas de travail"
            exception = ""
            diff_premier_entrie = 0
            diff_pause_sortie = 0
            diff_entrer_apres_pause = 0
            diff_sortie_final = 0
            sum_sortie_en_avance = 0
            sum_retard = 0
            sum_retard_general = 0
            heure_supp = "00:00:00"
        
        else:
            
    
            if status == "anomalie":
                diff_premier_entrie = 0
                diff_pause_sortie = 0
                diff_entrer_apres_pause = 0
                diff_sortie_final = 0
                sum_sortie_en_avance = 0
                sum_retard = 0
                sum_retard_general = 0
                heure_supp = "00:00:00"
            else:
                if status == "present":
                    if premier_entrie is None:
                        exception += "Pas d'entrée , "
                    if sortie_final is None:
                        exception += "Pas de sortie , "
                
                # Use user-specific schedule for calculations
                diff_premier_entrie = calculate_morning_delay(premier_entrie, user_schedule) if premier_entrie else 0
                diff_pause_sortie = calculate_lunch_early_departure(exits['s1'], user_schedule) if exits['s1'] else 0
                diff_entrer_apres_pause = calculate_afternoon_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                diff_sortie_final = calculate_evening_early_departure(sortie_final, user_schedule) if sortie_final else 0
                diff_pause_sortie = min(diff_pause_sortie, 240)
                
            # (mohasaratt) surround the 6th case with this if 
                if num_entries == 3 and num_exits == 3:
            # Get schedule thresholds
                    morning_start = datetime.strptime(user_schedule["MORNING_START"], "%H:%M:%S").time()
                    custom_morning_start = (datetime.combine(datetime.today(), morning_start) - timedelta(minutes=30)).time()
                    lunch_threshold = datetime.strptime(user_schedule["LUNCH_THRESHOLD"], "%H:%M:%S").time()
                    afternoon_threshold = datetime.strptime(user_schedule["AFTERNOON_THRESHOLD"], "%H:%M:%S").time()
                    evening_threshold = datetime.strptime(user_schedule["EVENING_THRESHOLD"], "%H:%M:%S").time()

                    # s1 and s2 between 9:00 and 12:45
                    if exits['s1'] and entries['e2']:
                        if (custom_morning_start <= exits['s1'] <= lunch_threshold) and (custom_morning_start <= entries['e2'] <= lunch_threshold):
                                # Add e2-s1 to SA1 (Sortie en avance 1)
                                delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                diff_pause_sortie = max(0, delta1)  
                        elif (exits['s1'] < custom_morning_start  ) and ( entries['e2'] < custom_morning_start):
                                diff_pause_sortie = 0
                        elif(custom_morning_start > exits['s1']) and (custom_morning_start >= entries['e1'] ):
                            diff_premier_entrie = calculate_morning_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                            diff_pause_sortie = calculate_lunch_early_departure(exits['s2'], user_schedule) if exits['s2'] else 0
                            diff_entrer_apres_pause = calculate_afternoon_delay(entries['e3'], user_schedule) if entries['e3'] else 0
                            diff_sortie_final = calculate_evening_early_departure(exits['s3'], user_schedule) if exits['s3'] else 0
                            diff_pause_sortie = min(diff_pause_sortie, 240)
                        elif (afternoon_threshold <= exits['s1'] <= evening_threshold) and (afternoon_threshold <= entries['e2'] <= evening_threshold):
                                # Add e2-s1 to SA2 (Sortie en avance 1) if value null 0 at return
                                delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                diff_entrer_apres_pause = max(0, delta1)
                                    # in minute
                    # s2 and e3 between 14:30 and 17:45
                    if exits['s2'] and entries['e3']:
                        if (afternoon_threshold <= exits['s2'] <= evening_threshold) and (afternoon_threshold <= entries['e3'] <= evening_threshold):
                            # Add e3-s2 to SA2 (Sortie en avance 2)
                            delta2 = (datetime.combine(datetime.today(), entries['e3']) - datetime.combine(datetime.today(), exits['s2'])).total_seconds() / 60
                            diff_sortie_final += max(0, delta2)  # in minutes
                        elif ( exits['s2'] <= afternoon_threshold) and (afternoon_threshold <= entries['e3'] <= evening_threshold):
                            diff_entrer_apres_pause = calculate_afternoon_delay(entries['e3'], user_schedule) if entries['e3'] else 0
                        elif  (custom_morning_start <= exits['s2'] <= lunch_threshold) and (custom_morning_start <= entries['e3'] <= lunch_threshold):
                            delta2 = (datetime.combine(datetime.today(), entries['e3']) - datetime.combine(datetime.today(), exits['s2'])).total_seconds() / 60
                            diff_sortie_final += max(0, delta2)
                        elif (afternoon_threshold <= exits['s2'] <= evening_threshold):
                            workday_end_dt = datetime.combine(datetime.today(), datetime.strptime("18:00:00", "%H:%M:%S").time())
                            exits_s2_dt = datetime.combine(datetime.today(), exits['s2'])
                            delta2 = (workday_end_dt - exits_s2_dt).total_seconds() / 60
                            diff_sortie_final += max(0, delta2)
                        

                if num_entries == 2 and num_exits == 2: 
                    # Get schedule thresholds
                    morning_start = datetime.strptime(user_schedule["MORNING_START"], "%H:%M:%S").time()
                    custom_morning_start = (datetime.combine(datetime.today(), morning_start) - timedelta(minutes=30)).time()
                    
                    lunch_threshold = datetime.strptime(user_schedule["LUNCH_THRESHOLD"], "%H:%M:%S").time()
                    afternoon_threshold = datetime.strptime(user_schedule["AFTERNOON_THRESHOLD"], "%H:%M:%S").time()
                    evening_threshold = datetime.strptime(user_schedule["WORKDAY_END"], "%H:%M:%S").time()

                    # s1 and s2 between 9:00 and 12:45
                    if exits['s1'] and entries['e2']:
                        if (custom_morning_start <= exits['s1'] <= lunch_threshold) and (custom_morning_start <= entries['e2'] <= lunch_threshold):
                                # Add e2-s1 to SA1 (Sortie en avance 1)
                                delta1 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                                diff_pause_sortie = max(0, delta1)  
                        elif (afternoon_threshold <= exits['s1'] <= evening_threshold) and (afternoon_threshold <= entries['e2'] <= evening_threshold):
                            # Add e3-s2 to SA2 (Sortie en avance 2)
                            delta2 = (datetime.combine(datetime.today(), entries['e2']) - datetime.combine(datetime.today(), exits['s1'])).total_seconds() / 60
                            diff_entrer_apres_pause = max(0, delta2)
                        elif(custom_morning_start > exits['s1']) and (custom_morning_start >= entries['e1'] ):
                            diff_premier_entrie = calculate_morning_delay(entries['e2'], user_schedule) if entries['e2'] else 0
                            diff_pause_sortie = 0
                            diff_entrer_apres_pause = 0
                            diff_sortie_final = calculate_evening_early_departure(exits['s2'], user_schedule) if exits['s2'] else 0
                        elif (exits['s2'] and exits['s2'] > evening_threshold) and (entries['e2'] and entries['e2'] > evening_threshold) and (exits['s1'] and afternoon_threshold <= exits['s1'] <= evening_threshold):
                            diff_sortie_final = calculate_evening_early_departure(exits['s1'], user_schedule)
                        elif (exits['s2'] and exits['s2'] > evening_threshold) and (entries['e2'] and entries['e2'] > evening_threshold) and (exits['s1'] and morning_start <= exits['s1'] <= afternoon_threshold):
                            diff_sortie_final = calculate_evening_early_departure(exits['s1'], user_schedule)

                if num_entries == 1 and num_exits == 1:
                    # Get schedule thresholds
                    afternoon_threshold = datetime.strptime(user_schedule["LUNCH_END"], "%H:%M:%S").time()  # 14:00
                    
                    # Case: if e1 is after 14:00, calculate both R1(e1) and R2(e1)
                    if entries['e1'] and entries['e1'] > afternoon_threshold:
                        # R1(e1) - Calculate morning delay based on e1 (max 4 hours = 240 minutes)
                        diff_premier_entrie = min(calculate_morning_delay(entries['e1'], user_schedule), 240)
                        
                        # R2(e1) - Calculate afternoon delay based on e1
                        diff_entrer_apres_pause = calculate_afternoon_delay(entries['e1'], user_schedule)
                        
                        # Keep existing SA2 calculation
                        diff_sortie_final = calculate_evening_early_departure(sortie_final, user_schedule) if sortie_final else 0

            if diff_premier_entrie > 0:
                exception += "Arriver en retard , "

            if diff_pause_sortie > 0:
                exception += "Depart anticipe pour pause , "

            if diff_entrer_apres_pause > 0:
                exception += "Retour en retard apres pause , "

            if diff_sortie_final > 0:
                exception += "Depart anticipe en fin de journee , "

            exception = exception.strip()
            if exception.endswith(','):
                exception = exception[:-1].strip()
           
            sum_sortie_en_avance = diff_pause_sortie + diff_sortie_final
            sum_retard = diff_premier_entrie + diff_entrer_apres_pause
            sum_retard_general = sum_retard + sum_sortie_en_avance
            if sum_retard_general < 0:
                sum_retard_general = 0


            if status == "anomalie":
                heure_supp = "00:00:00"
            else:
                all_entries = [entries[f'e{i}'] for i in range(1, 5) if entries[f'e{i}']]
                all_exits = [exits[f's{i}'] for i in range(1, 5) if exits[f's{i}']]
                heure_supp = calculate_heure_supp(all_entries, all_exits, user_schedule)
       

        
        record = {
            'Date': date.strftime('%Y-%m-%d'),
            'Nom': name,
            'ID utilisateur': user_id,
            'Département': department,
            'Status': status,
            'Exception': exception,
            'Premier Entree': premier_entrie.strftime('%H:%M:%S') if premier_entrie else None,
            'Sortie Final': sortie_final.strftime('%H:%M:%S') if sortie_final else None,
            'Retard 1 ': convert_minutes_to_hms(diff_premier_entrie),
            'Sortie en avance 1 ': convert_minutes_to_hms(diff_pause_sortie),
            'Retard 2 ': convert_minutes_to_hms(diff_entrer_apres_pause),
            'Sortie en avance 2 ': convert_minutes_to_hms(diff_sortie_final),
            'la somme Sortie en avance': convert_minutes_to_hms(min(sum_sortie_en_avance,480)),
            'la somme Retard': convert_minutes_to_hms(sum_retard),
            'la somme du Retards General': convert_minutes_to_hms(min(sum_retard_general, 480)),
            'Heure Supp': heure_supp,
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
                    exception = "Jour férié"
                    status = "Absent"
                else:
                    is_weekend_day = is_weekend(workday, user_id)
                    
                    if is_weekend_day:
                        exception = ""
                        status = "Pas de travail"
                    else:
                        exception = ""
                        status = "Absent"
                
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
        weekday_records = group[group['Status'] != "Pas de travail"]
        
        sortie_en_avnce = sum(time_to_minutes(x) for x in weekday_records['la somme Sortie en avance'])
        Retard = sum(time_to_minutes(x) for x in weekday_records['la somme Retard'])
        total_retard = sum(time_to_minutes(x) for x in weekday_records['la somme du Retards General'])
        total_heures_supp = sum(time_to_minutes(x) for x in weekday_records['Heure Supp'])
       
        absent_days = len(weekday_records[(weekday_records['Status'] == 'Absent') & (weekday_records['Exception'] != 'Jour férié')])
       
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
            'Total Sortie en avance': convert_minutes_to_hms(sortie_en_avnce),
            'Total Retard': convert_minutes_to_hms(Retard),
            'Total Retard General': convert_minutes_to_hms(total_retard),
            'Total Heures Supplementaires': convert_minutes_to_hms(total_heures_supp)
        })
   
    return pd.DataFrame(summary_data)

def process_attendance(data_json=None):
    try:
        if not data_json:
            data_json = sys.stdin.read()
        
        data = json.loads(data_json)
        result = process_attendance_data(data)
        return json.dumps(result)
    except Exception as e:
        return json.dumps({"error": f"Python Error: {str(e)}"})

def configure_user_schedule(user_id, schedule_type):
    """Configure a user's schedule type (standard, early, late)"""
    if schedule_type in DEFAULT_SCHEDULES:
        USER_SCHEDULES[user_id] = schedule_type
        return True
    return False

def add_custom_schedule(schedule_name, config):
    """Add a new custom schedule configuration"""
    if schedule_name not in DEFAULT_SCHEDULES:
        DEFAULT_SCHEDULES[schedule_name] = config
        return True
    return False

if __name__ == "__main__":
    try:
        # Initialize data from database before processing
        initialize_from_database()
        
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