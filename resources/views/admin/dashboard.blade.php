@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Tableau de bord Admin</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Horaires</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['schedules'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Employés</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['user_schedules'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Samedis Spéciaux</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['saturday_schedules'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Jours Fériés</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['public_holidays'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Périodes Ramadan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['ramadan_periods'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-moon fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Horaires par Jour</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['day_schedules'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Périodes Spéciales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['schedule_periods'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if((int) (Session::get('user')->droit ?? 1) === 0)
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Utilisateurs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['users'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Gestion des Horaires</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="{{ route('admin.schedules') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock mr-2"></i> Gérer les horaires
                        </a>
                        <a href="{{ route('admin.user-schedules') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-users mr-2"></i> Gérer les horaires des employés
                        </a>
                        <a href="{{ route('admin.saturday-schedules') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-day mr-2"></i> Gérer les horaires du samedi
                        </a>
                        <a href="{{ route('admin.user-workdays') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-week mr-2"></i> Gérer les jours de travail
                        </a>
                        <a href="{{ route('admin.public-holidays') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-times mr-2"></i> Gérer les jours fériés
                        </a>
                        <a href="{{ route('admin.ramadan-periods') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-moon mr-2"></i> Gérer les périodes Ramadan
                        </a>
                        <a href="{{ route('admin.user-day-schedules') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt mr-2"></i> Horaires par Jour
                        </a>
                        @if((int) (Session::get('user')->droit ?? 1) === 0)
                        <a href="{{ route('admin.roles') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-shield mr-2"></i> Gerer les roles et droits
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('footer')
