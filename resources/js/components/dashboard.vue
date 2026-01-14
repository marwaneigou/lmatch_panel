<template>
  <div class="football-dashboard">
    <!-- Header Stats -->
    <div class="row mb-5">
      <div class="col-12">
           <h2 class="section-title text-white mb-4"><i class="fa fa-chart-line mr-2"></i> Match Statistics</h2>
      </div>

       <!-- Active Codes -->
      <div class="col-md-3 mb-4">
        <div class="stat-card card-emerald">
            <div class="stat-icon"><i class="fa fa-ticket"></i></div>
            <div class="stat-content">
                <div class="stat-value">{{ counts.ActiveCode }}</div>
                <div class="stat-label">{{ trans('acCode_total') || 'Active Codes' }}</div>
            </div>
            <router-link to="/ActiveCode" class="stat-action">View Details <i class="fa fa-arrow-right"></i></router-link>
        </div>
      </div>

      <!-- Users -->
      <div class="col-md-3 mb-4">
        <div class="stat-card card-blue">
            <div class="stat-icon"><i class="fa fa-address-card"></i></div>
             <div class="stat-content">
                <div class="stat-value">{{ counts.Users }}</div>
                <div class="stat-label">{{ trans('users_total') || 'Total Users' }}</div>
            </div>
             <router-link to="/Users" class="stat-action">View Squad <i class="fa fa-arrow-right"></i></router-link>
        </div>
      </div>

      <!-- Mag Devices -->
      <div class="col-md-3 mb-4">
         <div class="stat-card card-purple">
            <div class="stat-icon"><i class="fa fa-desktop"></i></div>
             <div class="stat-content">
                <div class="stat-value">{{ counts.MagDevice }}</div>
                <div class="stat-label">{{ trans('mgDevice_total') || 'Mag Devices' }}</div>
            </div>
             <router-link to="/MagDevice" class="stat-action">View Devices <i class="fa fa-arrow-right"></i></router-link>
         </div>
      </div>

       <!-- Resellers -->
       <div class="col-md-3 mb-4">
         <div class="stat-card card-orange">
            <div class="stat-icon"><i class="fa fa-briefcase"></i></div>
             <div class="stat-content">
                <div class="stat-value">{{ counts.Resiler }}</div>
                <div class="stat-label">{{ trans('resellers') || 'Transfers' }}</div>
            </div>
             <router-link to="/Resilers" class="stat-action">View Resellers <i class="fa fa-arrow-right"></i></router-link>
         </div>
      </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row mb-5">
         <!-- Categories -->
        <div class="col-md-3 mb-4">
            <div class="mini-stat-card">
                <div class="mini-icon"><i class="fa fa-list"></i></div>
                <div class="mini-info">
                    <h4>{{ counts.Category }}</h4>
                    <p>{{ trans('categories') || 'Categories' }}</p>
                </div>
                 <router-link to="/Category" class="mini-link"></router-link>
            </div>
        </div>
        <!-- Channels -->
        <div class="col-md-3 mb-4">
            <div class="mini-stat-card">
                <div class="mini-icon"><i class="fa fa-tv"></i></div>
                <div class="mini-info">
                     <h4>{{ counts.Channel }}</h4>
                     <p>{{ trans('channels') || 'Channels' }}</p>
                </div>
                <router-link to="/Channel" class="mini-link"></router-link>
            </div>
        </div>
    </div>
      
    <!-- Players Section -->
    <div class="row mb-5">
          <div class="col-12">
            <h3 class="section-title text-white mb-4"><i class="fa fa-play-circle mr-2"></i> Player Lineup</h3>
          </div>
          
          <div class="col-12 text-center mb-4 main-player">
            <a href="https://naxplayer.com/device/dashboard" target="_blank" class="player-glow">
              <img src="assets/img/nax.png" class="img-fluid" style="max-height: 80px;" alt="NAX Player"/>
            </a>
          </div>

          <div class="col-12">
              <div class="d-flex flex-wrap justify-content-center gap-4 player-grid">
                    <a href="https://iboiptv.com/" target="_blank" class="player-item">
                    <img src="assets/img/ibo2_2.png" alt="IBO" />
                    </a>
                    <a href="https://iboplayer.pro/manage-playlists/login" target="_blank" class="player-item">
                    <img src="assets/img/ibopro.jpg" alt="IBO PRO" />
                    </a>
                    <a href="https://www.arcplayer.com/manage" target="_blank" class="player-item">
                    <img src="assets/img/arc_2.png" alt="ARC" />
                    </a>
                     <a href="https://siptv.eu/mylist/" target="_blank" class="player-item">
                    <img src="assets/img/smart_2.png" alt="Smart" />
                    </a>
                    <a href="https://setsysteme.com/set.app" target="_blank" class="player-item">
                    <img src="assets/img/set_2.png" alt="SET" />
                    </a>
                     <a href="http://www.wolftv.pro/list" target="_blank" class="player-item">
                      <img src="assets/img/wolf_2.png" alt="Wolf" />
                    </a>
                     <a href="https://cms.bayip.tv" target="_blank" class="player-item">
                      <img src="assets/img/bay_2.png" alt="Bay" />
                    </a>
                     <a href="https://manage.roomiptv.com/multi" target="_blank" class="player-item">
                      <img src="assets/img/room_2.png" alt="Room" />
                    </a>
                    <a href="https://www.netiptv.eu/upload" target="_blank" class="player-item">
                      <img src="assets/img/net_2.png" alt="Net" />
                    </a>
                     <a href="https://masaplayer.com/mylist" target="_blank" class="player-item">
                      <img src="assets/img/masa_2.png" alt="Masa" />
                    </a>
                     <a href="https://virginia-player.com/" target="_blank" class="player-item">
                      <img src="assets/img/virginia_2.png" alt="Virginia" />
                    </a>
              </div>
          </div>
    </div>

    <!-- Ads / Message Section -->
    <div class="row mb-5" v-show="counts.settings && counts.settings.ads != ''">
         <div class="col-12 text-center">
             <div class="ad-board">
                  <h4 class="text-emerald-400 mb-3">{{trans('ads')}}</h4>
                   <img style="height: 300px; object-fit: contain;" :src="getImgUrl(counts.settings.ads)">
             </div>
         </div>
    </div>

    <!-- Admin Table -->
    <div class="row mb-5" v-if="this.user_type == 'Admin'">
        <div class="col-12">
            <h3 class="section-title text-white mb-4"><i class="fa fa-users-cog mr-2"></i> {{trans('users_solde_2')}}</h3>
            
            <div class="table-responsive glass-table-container">
                <table class="table text-white mb-0">
                    <thead class="bg-emerald-900/50">
                    <tr>
                        <th class="border-0">#</th>
                        <th class="border-0">{{trans('username')}}</th>
                        <th class="border-0">{{trans('solde')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                        <tr v-if="onLoad">
                            <td colspan="3" class="text-center py-4">
                                <div class="spinner-border text-emerald-500" role="status"></div>
                            </td>
                        </tr>
                        <tr v-for="user in users_list.data" :key="user.id" class="border-b border-white/10 hover:bg-white/5">
                            <td>{{user.id}}</td>
                            <td>{{user.name}}</td>
                            <td><span class="badge badge-success">{{user.solde}}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <div class="mt-3">
                <vue-paginate-al
                :totalPage="this.users_list.last_page"
                activeBGColor="success"
                @btnClick="checkSolde"
                :withNextPrev="false"
                ></vue-paginate-al>
            </div>
        </div>
    </div>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Teko:wght@400;600&family=Inter:wght@400;500;700&display=swap');

.football-dashboard {
  font-family: 'Inter', sans-serif;
  padding: 2rem;
  /* Assuming a global dark bg via body, but enforcing transparency here */
}

/* Glass Table */
.glass-table-container {
    background: rgba(15, 23, 42, 0.6);
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

/* Typography */
.section-title {
    font-family: 'Teko', sans-serif;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 1.8rem;
    border-left: 4px solid #10b981; /* Emerald */
    padding-left: 1rem;
    font-weight: 600;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
}

.section-title i {
    color: #10b981;
    margin-right: 10px;
    filter: drop-shadow(0 0 5px rgba(16,185,129,0.6));
}

/* Premium Stat Cards - Flexbox Fallback */
.stat-card {
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.85) 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 14px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    
    /* Flex Layout - Bulletproof */
    display: flex;
    flex-wrap: wrap; /* Allows wrapping */
    align-items: center;
    justify-content: space-between;
    
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 1; /* Explicit z-index */
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    z-index: 0;
}

.stat-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.2);
}

/* Card Variants with Glows */
.card-emerald:hover { box-shadow: 0 10px 30px -10px rgba(16, 185, 129, 0.3); border-color: rgba(16, 185, 129, 0.3); }
.card-blue:hover { box-shadow: 0 10px 30px -10px rgba(59, 130, 246, 0.3); border-color: rgba(59, 130, 246, 0.3); }
.card-purple:hover { box-shadow: 0 10px 30px -10px rgba(168, 85, 247, 0.3); border-color: rgba(168, 85, 247, 0.3); }
.card-orange:hover { box-shadow: 0 10px 30px -10px rgba(249, 115, 22, 0.3); border-color: rgba(249, 115, 22, 0.3); }

.stat-icon {
    /* No Grid properties */
    flex: 0 0 auto;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-right: 15px; /* Spacing */
    background: rgba(255, 255, 255, 0.05); /* Slightly lighter for visibility */
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: inset 0 0 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    z-index: 2;
    position: relative; /* Ensure it's above background */
    color: inherit; /* Inherit from card variant specific color */
}

.card-emerald .stat-icon { color: #34d399 !important; }
.card-blue .stat-icon { color: #60a5fa !important; }
.card-purple .stat-icon { color: #c084fc !important; }
.card-orange .stat-icon { color: #fb923c !important; }

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
    background: rgba(255, 255, 255, 0.1);
}

.stat-content {
    /* Flex item taking remaining space */
    flex: 1 1 auto;
    text-align: right;
    display: flex;
    flex-direction: column;
    justify-content: center;
    z-index: 2;
    position: relative;
    min-width: 0; /* Prevent flex overflow */
}

.stat-value {
    font-family: 'Teko', sans-serif;
    font-size: 3rem; /* Larger */
    font-weight: 500;
    color: #ffffff !important; /* Force White */
    line-height: 0.9;
    margin-bottom: 0px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5); /* Stronger shadow */
}

.stat-label {
    text-transform: uppercase;
    font-size: 0.85rem;
    font-weight: 700;
    color: #cbd5e1 !important; /* Lighter Slate */
    letter-spacing: 1px;
    margin-top: 4px;
}

.stat-action {
    grid-column: 1 / -1;
    margin-top: 0.5rem;
    padding: 0.5rem 0;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    text-transform: uppercase;
    letter-spacing: 1px;
    text-decoration: none;
    display: flex;
    align-items: center;
    transition: all 0.3s;
    font-weight: 600;
}

.stat-action i {
    margin-left: 0.5rem;
    transition: transform 0.3s;
}

.stat-action:hover {
    color: #fff;
    text-shadow: 0 0 10px rgba(255,255,255,0.3);
}

.stat-action:hover i {
    transform: translateX(5px);
}

/* Mini Stats Redesign */
.mini-stat-card {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    position: relative;
    backdrop-filter: blur(8px);
    transition: all 0.3s ease;
}

.mini-stat-card:hover {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255,255,255,0.1);
    transform: translateY(-3px);
}

.mini-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #10b981;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.2rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.05);
}

.mini-info h4 {
    font-family: 'Teko', sans-serif;
    font-size: 1.8rem;
    color: #fff;
    margin: 0;
    line-height: 1;
}

.mini-info p {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #cbd5e1;
    margin: 0;
}
/* Players Grid */
.player-grid {
    gap: 1.5rem;
}
.player-item img {
    max-width: 100px;
    transition: transform 0.3s ease, filter 0.3s ease;
    filter: brightness(0.8) grayscale(0.5);
    border-radius: 8px;
}
.player-item:hover img {
    transform: scale(1.1);
    filter: brightness(1) grayscale(0);
}
.player-glow img {
     filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.5));
}
</style>

<script>
export default {
  mounted() {
    this.getUserAuth();
    this.getCount();   
  },
  data() {
    return {
      user_type: "",
      user_solde: "",
      counts: {},
      users_list:[],
      onLoad:false
    };
  },
  methods: {
    getUserAuth() {
      axios.post("code/GetUserAuth").then((response) => {
        this.user_type = response.data.user_type;
        this.user_solde = response.data.user_solde;
        if(this.user_type == 'Admin') {
          this.checkSolde();
        }
      });
    },

    getCount() {
      axios.post("code/GetCount").then(({ data }) => (this.counts = data));
    },

    checkSolde(page = 1) {
      this.onLoad = true;
      axios.post("code/checkSolde?page="+page).then(({ data }) => (this.users_list = data, this.onLoad = false));
    },

    getImgUrl(img) {
      var images = "/assets/img/" + img;
      return images;
    },
  },

  created() {
    this.getUserAuth();
    this.getCount();
  }
};
</script>
