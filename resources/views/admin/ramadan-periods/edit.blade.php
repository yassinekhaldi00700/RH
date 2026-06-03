@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Modifier la Période de Ramadan</h1>
        <a href="{{ route('admin.ramadan-periods') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informations de la Période</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.ramadan-periods.update', $period->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ramadan_start">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control @error('ramadan_start') is-invalid @enderror" 
                                   id="ramadan_start" 
                                   name="ramadan_start" 
                                   value="{{ old('ramadan_start', $period->ramadan_start) }}" 
                                   required>
                            @error('ramadan_start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ramadan_end">Date de Fin <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control @error('ramadan_end') is-invalid @enderror" 
                                   id="ramadan_end" 
                                   name="ramadan_end" 
                                   value="{{ old('ramadan_end', $period->ramadan_end) }}" 
                                   required>
                            @error('ramadan_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                @php
                    $start = \Carbon\Carbon::parse($period->ramadan_start);
                    $end = \Carbon\Carbon::parse($period->ramadan_end);
                    $duration = $start->diffInDays($end) + 1;
                @endphp

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Période actuelle:</strong> {{ $start->format('d/m/Y') }} - {{ $end->format('d/m/Y') }} ({{ $duration }} jours)
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre à Jour</button>
                    <a href="{{ route('admin.ramadan-periods') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('ramadan_start').addEventListener('change', function() {
    document.getElementById('ramadan_end').min = this.value;
});

// Set initial min for end date
document.getElementById('ramadan_end').min = document.getElementById('ramadan_start').value;
</script>

@include('footer')
