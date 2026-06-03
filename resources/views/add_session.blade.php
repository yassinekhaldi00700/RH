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
   
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
<!-- filepath: c:\Users\hp\Desktop\reader\resources\views\add_session.blade.php -->
<link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
    <!-- Custom styles for this template-->
   

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    @include('header')
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <h1 class="h3 mb-4 text-gray-800">Blank Page</h1>
                    <form action="{{ route('sessions.store') }}" method="POST">
    @csrf

    <div class="form-group">
        <label for="room_id">Room</label>
        <select class="form-control" id="room_id" name="room_id" required>
            <option value="">-- Select Room --</option>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}">{{ $room->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="class_id">Class</label>
        <select class="form-control" id="class_id" name="class_id" required>
            <option value="">-- Select Class --</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}">{{ $class->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="session_date">Session Date</label>
        <input type="date" class="form-control" name="session_date" required>
    </div>

    <div class="form-group">
        <label for="start_time">Start Time</label>
        <input type="time" class="form-control" name="start_time" required>
    </div>

    <div class="form-group">
        <label for="end_time">End Time</label>
        <input type="time" class="form-control" name="end_time" required>
    </div>

    <button type="submit" class="btn btn-primary">Add Session</button>
    <a href="{{route('admin.liste_session')}}" class="btn btn-light btn-icon-split">
    <span class="icon text-gray-600"><i class="fas fa-arrow-right"></i></span>
    <span class="text">List of sessions</span>
  </a>
</form>


                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            @include('footer')
    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

</body>

</html>