<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>gestion de présence</title>

    <!-- Custom fonts for this template-->
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   
   <style>
    :root {
            --primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-solid: #667eea;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --purple: #6f42c1;
            
            /* Grays */
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-300: #e0e0e0;
            --gray-400: #bdbdbd;
            --gray-500: #9e9e9e;
            --gray-600: #757575;
            --gray-700: #616161;
            --gray-800: #424242;
            --gray-900: #212121;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.04);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            
            /* Transitions */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
            color: var(--gray-700);
            line-height: 1.6;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem 2rem 2rem;
        }

        /* Logo Styles */
        .navbar-brand {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            margin-right: 1rem;
        }
        
        .navbar-brand img {
            height: 60px;
            width: auto;
            transition: var(--transition);
            margin-left: 25px;
        }
        
        .navbar-brand:hover img {
            transform: scale(1.05);
            opacity: 0.9;
        }

        /* Header Styles */
        .header-section {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--gray-800);
        }

        .main-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
            text-shadow: 0 2px 4px rgba(255,255,255,0.3);
        }

        .main-title i {
            margin-right: 1rem;
            color: var(--primary-solid);
        }

        .subtitle {
            font-size: 1.2rem;
            font-weight: 300;
            color: var(--gray-600);
            opacity: 1;
        }

        /* Card Styles */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: none;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem 2rem;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
        }

        .card-header h3 i {
            margin-right: 0.5rem;
            color: var(--primary-solid);
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Styles */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-item label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .filter-item label i {
            margin-right: 0.5rem;
            color: var(--primary-solid);
            width: 16px;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-solid);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .elegant-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            appearance: none;
        }

        /* Date Range */
        .date-range-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .date-separator {
            font-weight: 500;
            color: var(--gray-500);
        }

        /* Search Container */
        .search-container {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }

        .search-result-item:hover {
            background: var(--gray-50);
        }

        .search-result-item i {
            margin-right: 0.5rem;
            color: var(--primary-solid);
        }

        .no-results, .error-result {
            padding: 1rem;
            text-align: center;
            color: var(--gray-500);
            font-style: italic;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            gap: 0.5rem;
        }

        .elegant-btn {
            position: relative;
            overflow: hidden;
        }

        .elegant-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition);
        }

        .elegant-btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-500);
            color: var(--white);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-info {
            background: var(--info);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--gray-800);
        }

        .btn-outline-success {
            background: transparent;
            color: var(--success);
            border: 2px solid var(--success);
        }

        .btn-outline-info {
            background: transparent;
            color: var(--info);
            border: 2px solid var(--info);
        }

        .btn-outline-success:hover,
        .btn-outline-info:hover {
            background: var(--success);
            color: var(--white);
        }

        .btn-outline-info:hover {
            background: var(--info);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .action-section {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        /* Column Selection */
        .column-actions {
            display: flex;
            gap: 0.5rem;
        }

        .columns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .column-category {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .category-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            color: var(--gray-700);
        }

        .category-title i {
            margin-right: 0.5rem;
        }

        .checkbox-group {
            display: grid;
            gap: 0.75rem;
        }

        /* Elegant Checkboxes */
        .elegant-checkbox {
            position: relative;
            display: flex;
            align-items: center;
        }

        .elegant-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .elegant-checkbox label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 400;
            color: var(--gray-700);
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: var(--radius-sm);
        }

        .elegant-checkbox:hover label {
            background: var(--white);
            color: var(--primary-solid);
        }

        .checkmark {
            height: 20px;
            width: 20px;
            background-color: var(--white);
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-sm);
            margin-right: 0.75rem;
            position: relative;
            transition: var(--transition);
        }

        .elegant-checkbox input:checked ~ label .checkmark {
            background-color: var(--primary-solid);
            border-color: var(--primary-solid);
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .elegant-checkbox input:checked ~ label .checkmark:after {
            display: block;
        }

        .elegant-checkbox label i {
            margin-right: 0.5rem;
            width: 16px;
        }

        /* Table Styles */
        .elegant-table {
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .elegant-table .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .elegant-table .table thead th {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
            padding: 1rem;
            border: none;
            position: relative;
        }

        .elegant-table .table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .elegant-table .table tbody tr:hover {
            background: var(--gray-50);
        }

        .elegant-table .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Export Section */
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .export-section {
            text-align: center;
        }

        .export-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .export-section h4 i {
            margin-right: 0.5rem;
        }

        .export-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        /* Results Info */
        .results-info {
            display: flex;
            gap: 1rem;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary {
            background: var(--primary-solid);
            color: var(--white);
        }

        /* Color Utilities */
        .text-primary { color: var(--primary-solid) !important; }
        .text-success { color: var(--success) !important; }
        .text-info { color: var(--info) !important; }
        .text-warning { color: var(--warning) !important; }
        .text-danger { color: var(--danger) !important; }
        .text-purple { color: var(--purple) !important; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .main-title {
                font-size: 2rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .date-range-container {
                flex-direction: column;
                gap: 0.5rem;
            }

            .columns-grid {
                grid-template-columns: 1fr;
            }

            .export-grid {
                grid-template-columns: 1fr;
            }

            .action-section {
                flex-direction: column;
            }
        }
   </style>
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
<!-- filepath: c:\Users\hp\Desktop\reader\resources\views\add_session.blade.php -->
<link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
<link href="{{ asset('css/sb-admin-2.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
    <!-- Custom styles for this template-->
   

</head>

<body id="page-top">

<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Logo -->
    <a class="navbar-brand" href="{{ route('add_session') }}" title="Page Principale">
        <img src="{{ asset('img/logo.png') }}" alt="UEMF Logo">
    </a>

<!-- Topbar Search -->


<!-- Topbar Navbar -->
<ul class="navbar-nav ml-auto">

    <!-- Nav Item - Search Dropdown (Visible Only XS) -->
    <li class="nav-item dropdown no-arrow d-sm-none">
        <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-search fa-fw"></i>
        </a>
        <!-- Dropdown - Messages -->
        <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
            aria-labelledby="searchDropdown">
            <form class="form-inline mr-auto w-100 navbar-search">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0 small"
                        placeholder="Search for..." aria-label="Search"
                        aria-describedby="basic-addon2">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button">
                            <i class="fas fa-search fa-sm"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </li>
    

    <!-- Nav Item - Alerts -->
   
    @if(Session::has('user') && Session::get('user')->role === 'admin')
    <!-- Admin Dashboard Link -->
    <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.dashboard') }}" title="Administration">
            <i class="fas fa-cog fa-fw"></i>
            <span class="d-none d-lg-inline">Admin</span>
        </a>
    </li>
    @endif

    <div class="topbar-divider d-none d-sm-block"></div>

    <!-- Nav Item - User Information -->
    <li class="nav-item dropdown no-arrow">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="mr-2 d-none d-lg-inline text-gray-600 small">{{ Session::get('user')->name }}</span>
            <img class="img-profile rounded-circle"
                src="img/{{ Session::get('user')->image }}">
        </a>
        <!-- Dropdown - User Information -->
        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
            aria-labelledby="userDropdown">
            
            <div class="dropdown-divider"></div>
            <a href="{{ route('logout') }}" class=" dropdown-item">
    Déconnexion
</a>
        </div>
    </li>

</ul>

</nav>
<!-- Admin Sidebar -->
@if(Session::has('user') && Session::get('user')->role === 'admin')
<div id="wrapper" style="display: flex;">
    <!-- Sidebar -->
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar" style="min-height: 100vh; width: 250px;">
        
        <!-- Sidebar - Brand -->
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('admin.dashboard') }}">
            <div class="sidebar-brand-icon">
                <i class="fas fa-cog"></i>
            </div>
            <div class="sidebar-brand-text mx-3">Admin Panel</div>
        </a>

        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <!-- Nav Item - Attendance Management -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('add_session') }}">
                <i class="fas fa-fw fa-calendar-check"></i>
                <span>Gestion des Présences</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Gestion des Horaires
        </div>

        <!-- Nav Item - Dashboard -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.dashboard') }}">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
        </li>

        <!-- Nav Item - Schedules -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.schedules') }}">
                <i class="fas fa-fw fa-clock"></i>
                <span>Calendrier</span>
            </a>
        </li>

        <!-- Nav Item - User Schedules -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.user-schedules') }}">
                <i class="fas fa-fw fa-users"></i>
                <span>Affectation au calendrier</span>
            </a>
        </li>

        <!-- Nav Item - Saturday Schedules -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.saturday-schedules') }}">
                <i class="fas fa-fw fa-calendar-day"></i>
                <span>Horaires du samedi</span>
            </a>
        </li>

        <!-- Nav Item - User Workdays -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.user-workdays') }}">
                <i class="fas fa-fw fa-calendar-week"></i>
                <span>Ajouter un jour de travaille</span>
            </a>
        </li>

        <!-- Nav Item - Public Holidays -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.public-holidays') }}">
                <i class="fas fa-fw fa-calendar-times"></i>
                <span>Jours fériés</span>
            </a>
        </li>

        <!-- Nav Item - Ramadan Periods -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.ramadan-periods') }}">
                <i class="fas fa-fw fa-moon"></i>
                <span>Périodes Ramadan</span>
            </a>
        </li>

        <!-- Nav Item - Horaires par Jour -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.user-day-schedules') }}">
                <i class="fas fa-fw fa-calendar-alt"></i>
                <span>Horaires par Jour</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Profils
        </div>

        <!-- Nav Item - Profile AC/AD -->
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.profile-acad') }}">
                <i class="fas fa-fw fa-id-card"></i>
                <span>Profil AC / AD</span>
            </a>
        </li>

        @if((int) (Session::get('user')->droit ?? 1) === 0)
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.roles') }}">
                <i class="fas fa-fw fa-user-shield"></i>
                <span>Roles</span>
            </a>
        </li>
        @endif

        <!-- Divider -->
        <hr class="sidebar-divider d-none d-md-block">

    </ul>
    <!-- End of Sidebar -->

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column" style="flex: 1;">
        <div id="content">
@else
<div id="wrapper">
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
@endif
