@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Nouvelle Période de Ramadan</h1>
        <a href="{{ route('admin.ramadan-periods') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informations de la Période</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.ramadan-periods.store') }}" method="POST">
                @csrf
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ramadan_start">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control @error('ramadan_start') is-invalid @enderror" 
                                   id="ramadan_start" 
                                   name="ramadan_start" 
                                   value="{{ old('ramadan_start') }}" 
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
                                   value="{{ old('ramadan_end') }}" 
                                   required>
                            @error('ramadan_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> La période de Ramadan sera automatiquement appliquée aux horaires configurés comme "Ramadan" dans la gestion des horaires.
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer la Période</button>
                    <a href="{{ route('admin.ramadan-periods') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('ramadan_start').addEventListener('change', function() {
    const startDate = new Date(this.value);
    const endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 29); // Ramadan is typically 29-30 days
    
    const endInput = document.getElementById('ramadan_end');
    if (!endInput.value) {
        endInput.value = endDate.toISOString().split('T')[0];
    }
    endInput.min = this.value;
});
</script>

@include('footer')
