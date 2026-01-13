
<!DOCTYPE html>

<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <!-- <link href="toastr.css" rel="stylesheet"/> -->
  <title>CODE ACTIVE</title>
  <script src="https://use.fontawesome.com/777929a57a.js"></script>

  <link rel="stylesheet"  href="/css/app.css?v=2.1">
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

  <?php if(Auth::check()): ?> 
    <meta name="user-type" content="<?php echo e(Auth::user()->type); ?>"> 
    <meta name="user-solde" content="<?php echo e(Auth::user()->solde + Auth::user()->gift); ?>"> 
    <meta name="user-solde-test" content="<?php echo e(Auth::user()->solde_test); ?>">     
    <meta name="user-gift" content="<?php echo e(Auth::user()->gift); ?>">     
    <meta name="user-solde-app" content="<?php echo e(Auth::user()->solde_app); ?>">     
    <meta name="show-message" content="<?php echo e(Auth::user()->show_message); ?>">     
    <meta name="session_token" content="<?php echo e($token); ?>"> 
    <meta name="testactive" content="<?php echo e($setting->test_active); ?>"> 
    <meta name="lang" content="<?php echo e(app()->getLocale()); ?>">
  <?php endif; ?>

  <style>
    * {
      font-family: 'Montserrat', sans-serif;
    }
    nav.main-header.navbar.navbar-icon-top.navbar-expand-lg.navbar-dark {
      background-color: #e8cb72 !important;
    }
    .navbar-dark .navbar-nav .nav-link, .nav-link>a {
        color: #fff !important;
    } 
    /* .router-link-exact-active {
      background-color: #D8BF62 !important;
    } */
    table .fa {
      font-size: 19px;
    }
    marquee {
        /* background: #D3D3D3; */
        height: auto;
        padding: 9px;
        color: gray;
        /* color: #e8cb72; */
        font-size: 20px;
        font-weight: bold;
        margin-left: 10px;
    }

    .btn-success, .btn-primary, .btn-info {
      background-color: #e8cb72;
      border-color: #e8cb72;
      color:black !important;
    }
    .btn-success:hover, .btn-primary:hover {
      background-color: #e8cb72;
      border-color: #e8cb72;
      box-shadow: none !important;
    }
    .btn-success:focus, .btn-success.focus, .btn-primary:focus, .btn-primary.focus {
      background-color: #e8cb72;
      border-color: #e8cb72;
      box-shadow: none !important;
    }
    /* .btn {
      color: white !important;
    } */
    .bg-success, .success {
      background-color: #e8cb72 !important;
    }
    .modal-header {
      /* background-color: #302b63 !important; */
      background-color: #e8cb72 !important;
    }
    .modal-header button.close {
      color: white !important;
    }
    .btn-danger {
      background-color: #DC143C	!important;
    }
    .badge-danger {
      background-color: #DC143C;
    }
    
    .inner {
      display: flex;
      justify-content: space-between;
    }
    .content-wrapper {
      background-color: #fff;
    }

    @media  only screen and (max-width: 1200px) {
      .navbar-expand-lg .navbar-nav .nav-link {
        padding-right: 0.1rem !important;
        padding-left: 0.1rem !important;
      }
    }

    @media  only screen and (max-width: 576px) {
      .router-link-exact-active {
        width: 100%;
      }
    }

    .btn-info:hover, .btn-info:not(:disabled):not(.disabled):active, .btn-info:not(:disabled):not(.disabled).active, .show > .btn-info.dropdown-toggle, .btn-info:focus, .btn-info.focus {
      background-color: #e8cb72;
      border-color: #e8cb72;
    }


    /*
    DEMO STYLE
*/

@import  "https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700";
body {
    font-family: 'Poppins', sans-serif;
    background: #fafafa;
}

p {
    font-family: 'Poppins', sans-serif;
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
    background: #fff;
    border: none;
    border-radius: 0;
    margin-bottom: 40px;
    box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
}

.navbar-btn {
    box-shadow: none;
    outline: none !important;
    border: none;
}

.line {
    width: 100%;
    height: 1px;
    border-bottom: 1px dashed #ddd;
    margin: 40px 0;
}

/* ---------------------------------------------------
    SIDEBAR STYLE
----------------------------------------------------- */

.wrapper {
    display: flex;
    width: 100%;
    align-items: stretch;
}

#sidebar {
    min-width: 250px;
    max-width: 250px;
    background: #000;
    color: #fff;
    transition: all 0.3s;
}

#sidebar.active {
    margin-left: -250px;
}

#sidebar .sidebar-header {
    padding: 20px;
    background: #000;
}

#sidebar ul.components {
    padding: 20px 0;
    border-bottom: 1px solid #47748b;
}

#sidebar ul p {
    color: #fff;
    padding: 10px;
}

#sidebar ul li a {
    padding: 10px;
    font-size: 1.1em;
    display: block;
   
}

@keyframes  myfirst {
  0%   {margin-left: 250px;}
  100% {margin-left: 0px;}
}

#sidebar ul li.active>a,
a[aria-expanded="true"] {
    color: #fff;
    background: #302b63;
}

a[data-toggle="collapse"] {
    position: relative;
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
    background: #302b63;
}

ul.CTAs {
    padding: 20px;
}

ul.CTAs a {
    text-align: center;
    font-size: 0.9em !important;
    display: block;
    border-radius: 5px;
    margin-bottom: 5px;
}

a.download {
    background: #fff;
    color: #302b63;
}

a.article,
a.article:hover {
    background: #e8cb72 !important;
    color: #fff !important;
}

/* ---------------------------------------------------
    CONTENT STYLE
----------------------------------------------------- */

#content {
    width: 100%;
    padding: 20px;
    min-height: 100vh;
    transition: all 0.3s;
}

/* ---------------------------------------------------
    MEDIAQUERIES
----------------------------------------------------- */

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

  @media (min-width: 992px) {
      .modal-lg, .modal-xl {
          max-width: 1000px;
      }
      .bq_items {
        column-count:4;width: 100%;
      }
  }

  @media (max-width: 992px) {
      .bq_items {
        column-count:1;width: 100%;
      }
  }



.list-unstyled {
  font-family: 'Raleway', Arial, sans-serif;
  text-transform: uppercase;
  font-weight: 500;
  letter-spacing: 1px;
}
.list-unstyled * {
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  -webkit-transition: all 0.35s ease;
  transition: all 0.35s ease;
}
.list-unstyled li {
  display: inline;
  list-style: outside none none;
  margin: 0.6px 0;
  padding: 0;
}
.list-unstyled a {
  padding: 0.3em 0;
  /* color: silver !important; */
  position: relative;
  text-decoration: none;
  display: inline-block;
}
.nav-link {
  color: silver;
}
.list-unstyled a:before,
.list-unstyled a:after {
  height: 3px;
  position: absolute;
  content: '';
  -webkit-transition: all 0.35s ease;
  transition: all 0.35s ease;
  background-color: #e8cb72;
  width: 0;
}
.list-unstyled a:before {
  top: 0;
  left: 0;
}
.list-unstyled a:after {
  bottom: 0;
  right: 0;
}
.list-unstyled a:hover,
.list-unstyled .current a {
  color: rgba(255, 255, 255, 0.5) !important;
}
.list-unstyled a:hover:before,
.list-unstyled .current a:before,
.list-unstyled a:hover:after,
.list-unstyled .current a:after {
  width: 100%;
}

.router-link-exact-active {
  background-color: #e8cb72!important;
  color: #000 !important;
  padding: 10px;
  width: 100%;
}














.container-fluid .table thead tr th{
    background-color:#e8cb72;
    padding: 30px 10px;
    border: none;
    color: black;
}
td:nth-child(2n+1){
    background-color: #EDE7F6;
}
td:nth-child(2n){
    background-color: white;
}
.container-fluid .table tbody tr td {
    padding: 30px 10px;
    font-weight: 600;
}
.btn.btn-primary{
    background-color: #673AB7;
    padding: 10px;
    width: 80px;
    border: 1px solid #673AB7 ;
}
.btn.btn-primary:hover{
    background-color: #7f5dbb;
}
.c_btn {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  font-size: 25px;
  padding: 0;
}

.form_label {
  color:#673AB7;
}

.content-header {
  padding: 0px;
}

.content-header .container-fluid {
  padding: 0px;
}

  
  </style>

</head>
<body class="layout-top-nav">

    <div class="wrapper" id="app">
        <!-- Sidebar  -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex justify-content-center">
            <?php if(auth()->user()->type ==='Admin'): ?>
              <a href="/"> <img class="navbar-brand" src="<?php echo e('assets/img/'.$logo); ?>" style="width: 115px;"> </a>
            <?php else: ?>
              <a href="/"><img src="<?php echo e('assets/img/'.$logo); ?>"  alt="User Image"  style="width: 115px;"></a>
            <?php endif; ?>
            </div>
            <div>             
              <select name="lang" id="lang" class="form-control" onchange="setLang()">
                <?php $__currentLoopData = config('app.available_locales'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($locale); ?>" <?php if(app()->getLocale() == $locale): ?> selected="selected" <?php endif; ?>> <img src="<?php echo e('assets/img/flags/en.png'); ?>" alt=""> <?php echo e(__('lang.'.$locale)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <ul class="list-unstyled components">
                <li>
                    <router-link to="/" class="nav-link">
                      <i class="fa fa-tachometer" aria-hidden="true"></i>
                      <?php echo e(trans('lang.home')); ?>

                    </router-link>
                </li>
                <li>
                  <router-link to="/ActiveCode" class="nav-link">
                    <i class="fa fa-table" aria-hidden="true"></i>
                    <?php echo e(trans('lang.activeCode')); ?>

                  </router-link>
                </li>
                <li>
                  <router-link to="/Users" class="nav-link" data-title="users">
                    <i class="fa fa-users" aria-hidden="true"></i>
                    <?php echo e(trans('lang.users')); ?>

                  </router-link>
                </li>               
                
                <li>
                  <router-link to="/MagDevice" class="nav-link">
                    <i class="fa fa-mobile" style="font-size: 22px;" aria-hidden="true"></i>
                    <?php echo e(trans('lang.mgDevice')); ?>

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
                    <?php echo e(trans('lang.applications')); ?>

                  </router-link>
                </li>                
                <li>
                  <router-link to="/MassCode" class="nav-link">
                    <i class="fa fa-server" aria-hidden="true"></i>
                    <?php echo e(trans('lang.multiCode')); ?>

                  </router-link>
                </li>
                <?php if(auth()->user()->type ==='Admin'): ?>
                  <li>
                    <router-link to="/MasterCode" class="nav-link">
                      <i class="fa fa-server" aria-hidden="true"></i>
                      <?php echo e(trans('lang.mstrCode')); ?>

                    </router-link>
                  </li>
                <?php endif; ?>
                <li>
                  <router-link to="/Resilers" class="nav-link">
                    <i class="fa fa-users" aria-hidden="true"></i>
                    <?php if(auth()->user()->type ==='Admin'): ?>
                      <?php echo e(trans('lang.resellers')); ?>

                    <?php else: ?>
                      <?php echo e(trans('lang.subres')); ?>

                    <?php endif; ?>
                  </router-link>
                </li>
                <li>
                  <router-link to="/active" class="nav-link">
                    <i class="fa fa-link" aria-hidden="true"></i>
                    <?php echo e(trans('lang.api')); ?>

                  </router-link>
                </li>
                <li>
                  <router-link to="/Inbox" class="nav-link">
                    <i class="fa fa-ticket" aria-hidden="true"></i>
                    <?php echo e(trans('lang.tickets')); ?>

                  </router-link>
                </li>
                <li>
                  <router-link to="/profile" class="nav-link">
                    <i class="fa fa-cogs" aria-hidden="true"></i>
                    <?php echo e(trans('lang.config')); ?>

                  </router-link>
                </li>
                <li>
                  <router-link to="/speedtest" class="nav-link">
                    <i class="fa fa-search" aria-hidden="true"></i>
                    <?php echo e(trans('lang.stest')); ?>

                  </router-link>
                </li>
                <li>
                  <router-link to="/transactions" class="nav-link">
                    <i class="fa fa-exchange" aria-hidden="true"></i>
                    <?php echo e(trans('lang.transactions')); ?>

                  </router-link>
                </li>
                <?php if(auth()->user()->type ==='Admin'): ?>
                  <li>
                    <router-link to="/hosts" class="nav-link">
                      <i class="fa fa-link" aria-hidden="true"></i>
                      <?php echo e(trans('lang.hosts')); ?>

                    </router-link>
                  </li>
                <?php endif; ?>                
                <li>
                    <i class="nav-icon fa fa-sign-in-alt"></i>
                    <a  href="<?php echo e(route('logout')); ?>" style="color: aliceblue;"
                        onclick="event.preventDefault();  document.getElementById('logout-form').submit();">
                        <i class="fa fa-sign-out" aria-hidden="true"></i>
                        <?php echo e(trans('lang.logout')); ?>

                    </a>
                    <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" style="display: none;">
                      <?php echo csrf_field(); ?>
                    </form>


                    <form id="langForm" action="" method="POST" style="display: none;">
                      <?php echo csrf_field(); ?>
                    </form>
                </li>
                <?php if (! (auth()->user()->type ==='Admin')): ?>
                  <li class="nav-item  res">
                      <div class="nav-link p-0">
                           <!--<span class="res-nom"><?php echo e(auth()->user()->name); ?> </Span>-->
                           <strong class="res-credit d-block" style="color:yellow;">&nbsp; (<?php echo e(auth()->user()->solde); ?> &nbsp;<?php echo e(trans('lang.credits')); ?>)</strong>
                           <strong class="res-credit d-block" style="color:yellow;">&nbsp; (<?php echo e(auth()->user()->solde_test); ?> &nbsp;<?php echo e(trans('lang.creditsTest')); ?>)</strong>
                           <strong class="res-credit d-block" style="color:yellow;">&nbsp; (<?php echo e(auth()->user()->solde_app); ?> &nbsp;<?php echo e(trans('lang.creditsApp')); ?>)</strong>
                           <strong class="res-credit d-block" style="color:yellow;">&nbsp; (<?php echo e(auth()->user()->gift); ?> &nbsp;<?php echo e(trans('lang.gift')); ?>)</strong>
                      </div>
                  </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Page Content  -->
        <div id="content">

            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fa fa-bars"></i>
                    </button>
                    <!-- <button class="btn btn-dark d-inline-block d-lg-none ml-auto" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="fas fa-align-justify"></i>
                    </button> -->
                    <?php if($setting->bande): ?>
                      <marquee width="100%" direction="left" height="100px">
                        <?php echo e($setting->bande); ?>

                      </marquee>
                    <?php endif; ?>
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

<script src="/js/app.js?v=<?php echo e(time()); ?>"></script>
<!-- <script src="toastr.js"></script> -->

</body>

<script>
  $(document).ready(function () {
      $('#sidebarCollapse').on('click', function () {
          $('#sidebar').toggleClass('active');
      });      
      $('#content').on('click', function (e) {
        if (event.target.id != "sidebarCollapse" && event.target.className != "fa fa-bars") {
          if($('#sidebarCollapse')) {
            if($('#sidebar').hasClass('active')) {
              $('#sidebar').toggleClass('active');
            }
          }
        }
      });
  });

  async function setLang(e) {
    langForm.setAttribute('action', '/code/set_localization/'+lang.value);
    // langForm.setAttribute('action', '/code/set_localization/'+e);
    document.getElementById('langForm').submit();
  }
</script>
</html>
<?php /**PATH /home/admin/domains/newpanel.kingiptv.pro/project/resources/views/layouts/master.blade.php ENDPATH**/ ?>