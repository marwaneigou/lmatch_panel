<template>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-sm-6">  
                <div class="card">
                    <div class="card-header">
                        <h5>Backup your Database</h5>
                        
                    </div>
                    <div class="card-body ">
                        <p>Backup your *.SQL File</p> 
                        <a  href="/assets/DB/iptv.sql" target="_blank" class="btn btn-primary">Backup</a>
                    </div>
                </div>
            </div><!-- /.col -->
        
        </div><!-- /.row -->

     
       
    </div>

</template>

<script>
    export default {
        mounted() {
            console.log('Component mounted.')
        }
    }
</script>
