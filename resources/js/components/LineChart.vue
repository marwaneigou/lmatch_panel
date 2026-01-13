<script>
import { Bar } from 'vue-chartjs'

export default {
  extends: Bar,
  props: {
    chartdata: {
      default: null
    },
    options: {
      default: null
    }
  },
  mounted () {
    console.log("this.chartdata");
    console.log(this.chartdata);
    console.log("this.chartdata");
    
    this.renderChart(this.chartdata, this.options)
  }
}
</script>

<style>
</style>