<!DOCTYPE html>

<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <!-- <link href="toastr.css" rel="stylesheet"/> -->
  <title>CODE ACTIVE</title>
  <script src="https://use.fontawesome.com/777929a57a.js"></script>

  <link rel="stylesheet" href="/css/app.css?v=2.1">
  <script src="https://unpkg.com/vuejs-paginate@0.8.0"></script>

  <script src="https://use.fontawesome.com/777929a57a.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.min.js"></script>
  <script src="https://unpkg.com/vue-chartjs/dist/vue-chartjs.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/vue/2.5.2/vue.min.js"></script>
  <!-- CDNJS :: Sortable (https://cdnjs.com/) -->
  <script src="//cdn.jsdelivr.net/npm/sortablejs@1.8.4/Sortable.min.js"></script>
  <!-- CDNJS :: Vue.Draggable (https://cdnjs.com/) -->
  <script src="//cdnjs.cloudflare.com/ajax/libs/Vue.Draggable/2.20.0/vuedraggable.umd.min.js"></script>
  <!-- <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script> -->

  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mdbvue/lib/css/mdb.min.css"> -->

  <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script> -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500&display=swap" rel="stylesheet">

  @if(Auth::check())
    <meta name="user-type" content="{{ Auth::user()->type }}">
    <meta name="user-solde" content="{{ Auth::user()->solde + Auth::user()->gift }}">
    <meta name="user-solde-test" content="{{ Auth::user()->solde_test }}">
    <meta name="user-gift" content="{{ Auth::user()->gift }}">
    <meta name="user-solde-app" content="{{ Auth::user()->solde_app }}">
    <meta name="show-message" content="{{ Auth::user()->show_message }}">
    <meta name="session_token" content="{{$token}}">
    <meta name="testactive" content="{{$setting->test_active}}">
    <meta name="lang" content="{{ app()->getLocale() }}">
  @endif

  <style>
    * {
      font-family: 'Montserrat', sans-serif;
    }

    body {
      font-family: 'Montserrat', sans-serif;
      background: url('/assets/img/stadium.png') no-repeat center center fixed;
      background-size: cover;
      color: #f8fafc;
      position: relative;
    }

    body::before {
      content: "";
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.85);
      /* Slate 900 overlay */
      z-index: -1;
    }

    nav.main-header.navbar.navbar-icon-top.navbar-expand-lg.navbar-dark {
      background-color: rgba(30, 41, 59, 0.95) !important;
      /* Slate 800 */
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .navbar-dark .navbar-nav .nav-link,
    .nav-link>a {
      color: #cbd5e1 !important;
      /* Slate 300 */
    }

    .navbar-dark .navbar-nav .nav-link:hover,
    .nav-link>a:hover {
      color: #10b981 !important;
      /* Emerald */
    }

    table .fa {
      font-size: 19px;
    }

    marquee {
      height: auto;
      padding: 9px;
      color: #10b981;
      font-size: 20px;
      font-weight: bold;
      margin-left: 10px;
    }

    .btn-success,
    .btn-primary,
    .btn-info {
      background-color: #10b981;
      border-color: #10b981;
      color: #0f172a !important;
      font-weight: 600;
    }

    .btn-success:hover,
    .btn-primary:hover,
    .btn-info:hover {
      background-color: #059669;
      border-color: #059669;
      box-shadow: 0 0 10px rgba(16, 185, 129, 0.4) !important;
      color: #fff !important;
    }

    .bg-success,
    .success {
      background-color: #10b981 !important;
    }

    .modal-header {
      background-color: #1e293b !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      color: #10b981;
    }

    .modal-content {
      background-color: #0f172a;
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-header button.close {
      color: white !important;
    }

    .btn-danger {
      background-color: #be123c !important;
      /* Rose 700 */
      border: none;
    }

    .badge-danger {
      background-color: #be123c;
    }

    .inner {
      display: flex;
      justify-content: space-between;
    }

    .content-wrapper {
      background-color: transparent;
    }

    @media only screen and (max-width: 1200px) {
      .navbar-expand-lg .navbar-nav .nav-link {
        padding-right: 0.1rem !important;
        padding-left: 0.1rem !important;
      }
    }

    @media only screen and (max-width: 576px) {
      .router-link-exact-active {
        width: 100%;
      }
    }

    p {
      font-family: 'Montserrat', sans-serif;
    }

    a,
    a:hover,
    a:focus {
      color: inherit;
      text-decoration: none;
      transition: all 0.3s;
    }

    .navbar {
      padding: 15px 10px;
      background: rgba(30, 41, 59, 0.95);
      /* Slate 800 */
      border: none;
      border-radius: 0;
      margin-bottom: 40px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Sidebar Style */
    .wrapper {
      display: flex;
      width: 100%;
      align-items: stretch;
    }

    #sidebar {
      min-width: 250px;
      max-width: 250px;
      background: rgba(30, 41, 59, 0.95);
      /* Slate 800 */
      color: #fff;
      transition: all 0.3s;
      border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    #sidebar.active {
      margin-left: -250px;
    }

    #sidebar .sidebar-header {
      padding: 20px;
      background: #0f172a;
      /* Slate 900 */
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    #sidebar ul.components {
      padding: 20px 0;
      border-bottom: 1px solid #334155;
    }

    #sidebar ul p {
      color: #fff;
      padding: 10px;
    }

    #sidebar ul li a {
      padding: 10px;
      font-size: 1.1em;
      display: block;
      color: #cbd5e1;
    }

    #sidebar ul li a:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.05);
    }

    #sidebar ul li.active>a,
    a[aria-expanded="true"] {
      color: #0f172a;
      background: #10b981;
      /* Emerald */
    }

    /* Router Link Active */
    .router-link-exact-active {
      background-color: #10b981 !important;
      color: #0f172a !important;
      padding: 10px;
      width: 100%;
      font-weight: 600;
    }

    .dropdown-toggle::after {
      display: block;
      position: absolute;
      top: 50%;
      right: 20px;
      transform: translateY(-50%);
    }

    ul ul a {
      font-size: 0.9em !important;
      padding-left: 30px !important;
      background: #334155;
    }

    /* Content Style */
    #content {
      width: 100%;
      padding: 20px;
      min-height: 100vh;
      transition: all 0.3s;
      background-color: transparent;
    }

    @media (max-width: 768px) {
      #sidebar {
        margin-left: -250px;
      }

      #sidebar.active {
        margin-left: 0;
      }

      #sidebarCollapse span {
        display: none;
      }
    }

    .list-unstyled {
      font-family: 'Raleway', Arial, sans-serif;
      text-transform: uppercase;
      font-weight: 500;
      letter-spacing: 1px;
    }

    .list-unstyled a:before,
    .list-unstyled a:after {
      background-color: #10b981;
    }

    .main-footer {
      background: #1e293b;
      color: #fff;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding: 1rem;
    }

    /* Table Overrides */
    .container-fluid .table thead tr th {
      background-color: #1e293b;
      color: #10b981;
      border-bottom: 2px solid #10b981;
    }

    .container-fluid .table tbody tr td {
      background-color: transparent !important;
      /* Allow generic table styles */
      color: #cbd5e1;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    td:nth-child(2n+1),
    td:nth-child(2n) {
      background-color: transparent !important;
    }

    .form_label {
      color: #10b981;
    }

    /* Input overrides */
    select.form-control {
      background-color: #1e293b;
      color: white;
      border: 1px solid #334155;
    }
  </style>

</head>

<body class="layout-top-nav">

  <div class="wrapper" id="app">
    <!-- Sidebar  -->
    <nav id="sidebar">
      <div class="sidebar-header d-flex justify-content-center">
        @if(auth()->user()->type === 'Admin')
          <a href="/"> <img class="navbar-brand" src="{{'assets/img/' . $logo}}" style="width: 115px;"> </a>
        @else
          <a href="/"><img src="{{'assets/img/' . $logo}}" alt="User Image" style="width: 115px;"></a>
        @endif
      </div>
      <div>
        <select name="lang" id="lang" class="form-control" onchange="setLang()">
          @foreach (config('app.available_locales') as $locale)
            <option value="{{$locale}}" @if (app()->getLocale() == $locale) selected="selected" @endif> <img
                src="{{'assets/img/flags/en.png'}}" alt=""> {{ __('lang.' . $locale) }}</option>
          @endforeach
        </select>
      </div>
      <ul class="list-unstyled components">
        <li>
          <router-link to="/" class="nav-link">
            <i class="fa fa-tachometer" aria-hidden="true"></i>
            {{trans('lang.home')}}
          </router-link>
        </li>
        <li>
          <router-link to="/ActiveCode" class="nav-link">
            <i class="fa fa-table" aria-hidden="true"></i>
            {{trans('lang.activeCode')}}
          </router-link>
        </li>
        <li>
          <router-link to="/Users" class="nav-link" data-title="users">
            <i class="fa fa-users" aria-hidden="true"></i>
            {{trans('lang.users')}}
          </router-link>
        </li>

        <li>
          <router-link to="/MagDevice" class="nav-link">
            <i class="fa fa-mobile" style="font-size: 22px;" aria-hidden="true"></i>
            {{trans('lang.mgDevice')}}
          </router-link>
        </li>
        <li>
          <router-link to="/arcplayer" class="nav-link">
            <i class="fa fa-th-list" aria-hidden="true"></i>
            ARC Player
          </router-link>
        </li>
        <li>
          <router-link to="/applications" class="nav-link">
            <i class="fa fa-th-list" aria-hidden="true"></i>
            {{trans('lang.applications')}}
          </router-link>
        </li>
        <li>
          <router-link to="/MassCode" class="nav-link">
            <i class="fa fa-server" aria-hidden="true"></i>
            {{trans('lang.multiCode')}}
          </router-link>
        </li>
        @if(auth()->user()->type === 'Admin')
          <li>
            <router-link to="/MasterCode" class="nav-link">
              <i class="fa fa-server" aria-hidden="true"></i>
              {{trans('lang.mstrCode')}}
            </router-link>
          </li>
        @endif
        <li>
          <router-link to="/Resilers" class="nav-link">
            <i class="fa fa-users" aria-hidden="true"></i>
            @if(auth()->user()->type === 'Admin')
              {{trans('lang.resellers')}}
            @else
              {{trans('lang.subres')}}
            @endif
          </router-link>
        </li>
        <li>
          <router-link to="/active" class="nav-link">
            <i class="fa fa-link" aria-hidden="true"></i>
            {{trans('lang.api')}}
          </router-link>
        </li>
        <li>
          <router-link to="/Inbox" class="nav-link">
            <i class="fa fa-ticket" aria-hidden="true"></i>
            {{trans('lang.tickets')}}
          </router-link>
        </li>
        <li>
          <router-link to="/profile" class="nav-link">
            <i class="fa fa-cogs" aria-hidden="true"></i>
            {{trans('lang.config')}}
          </router-link>
        </li>
        <li>
          <router-link to="/speedtest" class="nav-link">
            <i class="fa fa-search" aria-hidden="true"></i>
            {{trans('lang.stest')}}
          </router-link>
        </li>
        <li>
          <router-link to="/transactions" class="nav-link">
            <i class="fa fa-exchange" aria-hidden="true"></i>
            {{trans('lang.transactions')}}
          </router-link>
        </li>
        @if(auth()->user()->type === 'Admin')
          <li>
            <router-link to="/hosts" class="nav-link">
              <i class="fa fa-link" aria-hidden="true"></i>
              {{trans('lang.hosts')}}
            </router-link>
          </li>
        @endif
        <li>
          <i class="nav-icon fa fa-sign-in-alt"></i>
          <a href="{{ route('logout') }}" style="color: aliceblue;"
            onclick="event.preventDefault();  document.getElementById('logout-form').submit();">
            <i class="fa fa-sign-out" aria-hidden="true"></i>
            {{trans('lang.logout')}}
          </a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
          </form>


          <form id="langForm" action="" method="POST" style="display: none;">
            @csrf
          </form>
        </li>
        @unless(auth()->user()->type === 'Admin')
          <li class="nav-item res mt-3 border-top border-white-10 pt-3">
            <div class="nav-link p-0 px-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-xs text-slate-400 uppercase tracking-wider">{{trans('lang.credits')}}</span>
                <strong class="text-emerald-400">{{auth()->user()->solde}}</strong>
              </div>

              @if(auth()->user()->solde_test > 0)
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-xs text-slate-400 uppercase tracking-wider">{{trans('lang.creditsTest')}}</span>
                  <strong class="text-emerald-400">{{auth()->user()->solde_test}}</strong>
                </div>
              @endif

              @if(auth()->user()->solde_app > 0)
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-xs text-slate-400 uppercase tracking-wider">{{trans('lang.creditsApp')}}</span>
                  <strong class="text-emerald-400">{{auth()->user()->solde_app}}</strong>
                </div>
              @endif

              @if(auth()->user()->gift > 0)
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-xs text-slate-400 uppercase tracking-wider">{{trans('lang.gift')}}</span>
                  <strong class="text-emerald-400">{{auth()->user()->gift}}</strong>
                </div>
              @endif
            </div>
          </li>
        @endunless
      </ul>
    </nav>

    <!-- Page Content  -->
    <div id="content">

      <nav class="navbar navbar-expand-lg navbar-dark bg-dark-theme">
        <div class="container-fluid">
          <button type="button" id="sidebarCollapse" class="btn btn-info">
            <i class="fa fa-bars"></i>
          </button>
          <!-- <button class="btn btn-dark d-inline-block d-lg-none ml-auto" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="fas fa-align-justify"></i>
                    </button> -->
          @if($setting->bande)
            <marquee width="100%" direction="left" height="100px">
              {{$setting->bande}}
            </marquee>
          @endif
        </div>
      </nav>


      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper" style="margin-left:0;">
        <!-- Content Header (Page header) -->
        <div class="content-header">
          <div class="container-fluid">
            <div class="row mb-2">

              <router-view></router-view>
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
        Copyright &copy; 2023 iActive.
      </footer>
    </div>
  </div>



  <!-- ./wrapper -->

  <script src="/js/app.js?v={{ time() }}"></script>
  <!-- <script src="toastr.js"></script> -->

</body>

<script>
  $(document).ready(function () {
    $('#sidebarCollapse').on('click', function () {
      $('#sidebar').toggleClass('active');
    });
    $('#content').on('click', function (e) {
      if (event.target.id != "sidebarCollapse" && event.target.className != "fa fa-bars") {
        if ($('#sidebarCollapse')) {
          if ($('#sidebar').hasClass('active')) {
            $('#sidebar').toggleClass('active');
          }
        }
      }
    });
  });

  async function setLang(e) {
    langForm.setAttribute('action', '/code/set_localization/' + lang.value);
    // langForm.setAttribute('action', '/code/set_localization/'+e);
    document.getElementById('langForm').submit();
  }
</script>

</html>