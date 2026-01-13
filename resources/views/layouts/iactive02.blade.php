
<!DOCTYPE html>

<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <!-- <link href="toastr.css" rel="stylesheet"/> -->
  <title>CODE ACTIVE</title>
  <script src="https://use.fontawesome.com/777929a57a.js"></script>

  <link rel="stylesheet"  href="/css/app.css">
  <script src="https://unpkg.com/vuejs-paginate@0.8.0"></script>

  <script src="https://use.fontawesome.com/777929a57a.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.min.js"></script>  
  <script src="https://unpkg.com/vue-chartjs/dist/vue-chartjs.min.js"></script>
  <script src="https://unpkg.com/vue-recaptcha@latest/dist/vue-recaptcha.js"></script>
  <script src="https://unpkg.com/vue-recaptcha@latest/dist/vue-recaptcha.min.js"></script>

  <script src="https://www.google.com/recaptcha/api.js?onload=vueRecaptchaApiLoaded&render=explicit" async defer></script> 


  <!-- <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script> -->

  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mdbvue/lib/css/mdb.min.css"> -->

  <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script> -->

  <meta name="user-type" content=""> 
  <meta name="user-solde" content=""> 
  <meta name="user-solde-test" content="">     
  <meta name="show-message" content=""> 
  <meta name="lang" content="{{ app()->getLocale() }}">

</head>
<body class="layout-top-nav">
<div class="wrapper" id="app" style="margin-left:0;">
  <nav class="main-header navbar navbar-icon-top navbar-expand-lg navbar-dark" style="background-color: #064272;margin-left:0;">

    <a href="/"><img src="{{'assets/img/logo.png'}}"  alt="User Image"  style="width: 115px;"></a>

    <!-- <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button> -->

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
     
         <p style="font-size: 55px;color: white; margin-left: 10px;">API Active</p>
      
  </div>

  </nav>


 
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper" style="margin-left:0;">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">

        <active02-component></active02-component>
        <!-- set progressbar -->
        <vue-progress-bar></vue-progress-bar>

        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
  <!-- Main Footer -->
  <footer class="main-footer" style="margin-left:0;">
    <!-- To the right -->
    <!-- <div class="float-right d-none d-sm-inline">
      <a href="/about"> About this Application </a>
    </div> -->
    <!-- Default to the left -->
    Copyright &copy; 2022 iActive.
  </footer>
</div>
<!-- ./wrapper -->

<script src="/js/app.js"></script>
<!-- <script src="toastr.js"></script> -->

</body>
</html>
