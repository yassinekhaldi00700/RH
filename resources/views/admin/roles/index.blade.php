@include('header')

@php
    $currentUser = Session::get('user');
    $droitLabels = [
        0 => '0 - Tous',
        1 => '1 - Personnel',
        2 => '2 - Doctorants',
        3 => '3 - Complex',
    ];
@endphp

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Roles et droits</h1>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('undo_role_change'))
        @php($undo = session('undo_role_change'))
        <div class="alert alert-warning d-flex align-items-center justify-content-between">
            <span>
                Derniere modification: {{ $undo['name'] }}. Vous pouvez restaurer l'ancien role/droit.
            </span>
            <form action="{{ route('admin.roles.undo') }}" method="POST" class="mb-0">
                @csrf
                <input type="hidden" name="user_id" value="{{ $undo['id'] }}">
                <input type="hidden" name="role" value="{{ $undo['role'] }}">
                <input type="hidden" name="droit" value="{{ $undo['droit'] }}">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-undo"></i> Annuler
                </button>
            </form>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Utilisateurs</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Droit</th>
                            <th>Derniere modification</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php($isCurrentUser = (int) $currentUser->id === (int) $user->id)
                            <tr>
                                <td>
                                    <strong>{{ $user->name }}</strong>
                                    @if($isCurrentUser)
                                        <span class="badge badge-info ml-2">Vous</span>
                                    @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <select name="role" form="role-form-{{ $user->id }}" class="form-control form-control-sm" @if($isCurrentUser) disabled @endif>
                                        <option value="user" @selected(($user->role ?? 'user') === 'user')>user</option>
                                        <option value="admin" @selected(($user->role ?? 'user') === 'admin')>admin</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="droit" form="role-form-{{ $user->id }}" class="form-control form-control-sm" @if($isCurrentUser) disabled @endif>
                                        @foreach($droitLabels as $value => $label)
                                            <option value="{{ $value }}" @selected((int) ($user->droit ?? 1) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>{{ $user->updated_at }}</td>
                                <td>
                                    @if($isCurrentUser)
                                        <button type="button" class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    @else
                                        <form id="role-form-{{ $user->id }}" action="{{ route('admin.roles.update', $user->id) }}" method="POST" class="mb-0">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i> Enregistrer
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">Aucun utilisateur trouve</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('footer')
