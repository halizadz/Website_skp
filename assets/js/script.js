const { createApp, ref, onMounted, nextTick, watch } = Vue;

const dashboardUtils = window.$dashboard?.utils || {};
const dashboardCharts = window.$dashboard?.charts || {};

const $checkMobile = dashboardUtils.checkMobile || (() => window.innerWidth < 992);
const $formatNumber = dashboardUtils.formatNumber || ((num) => num?.toString() || '0');
const $formatDate = dashboardUtils.formatDate || ((date) => date || '');
const $animateCounters = dashboardUtils.animateCounters || (() => {});
const $initDepartmentChart = dashboardCharts.initDepartmentChart || (() => {});

createApp({
    setup() {
        // State management
        const sidebarCollapsed = ref(JSON.parse(localStorage.getItem('sidebarCollapsed')) || false);
        const isMobile = ref(window.$dashboard.utils.checkMobile());
        
        // Data structure
        const dashboardData = ref({
            totalMahasiswa: 0,
            pendaftarBulanIni: 0,
            labels_jurusan: [],
            values_jurusan: [],
            recentRegistrations: []
        });

        // Chart refs and instances
        const departmentChartRef = ref(null);
        let chartJurusan = null;

        // Methods
        const toggleSidebar = () => {
            sidebarCollapsed.value = !sidebarCollapsed.value;
            localStorage.setItem('sidebarCollapsed', sidebarCollapsed.value);
            nextTick(resizeCharts);
        };

        const resizeCharts = () => {
            chartJurusan?.resize();
        };

        const initChart = (chartRef, initFunction, labels, values, extraParam = null) => {
            if (!chartRef.value) {
                console.error(`Canvas element not found for ${initFunction.name}`);
                return null;
            }
            
            try {
                const ctx = chartRef.value.getContext('2d');
                if (!ctx) throw new Error('Could not get 2D context');
                
                return extraParam 
                    ? initFunction(ctx, labels, values, extraParam)
                    : initFunction(ctx, labels, values);
            } catch (error) {
                console.error(`Chart initialization error:`, error);
                return null;
            }
        };

const initCharts = async () => {
    await nextTick();
    
    // Fallback 1: Cek ref Vue
    let canvas = departmentChartRef.value;
    
    // Fallback 2: Jika ref gagal, cari manual
    if (!canvas) {
        console.warn("Vue ref failed, trying document.querySelector...");
        canvas = document.querySelector('canvas');
    }
    
    // Fallback 3: Jika masih tidak ditemukan
    if (!canvas) {
    console.error("Canvas tidak ditemukan setelah semua upaya");
    return;
  }

    // Pastikan data tersedia
    if (!dashboardData.value.labels_jurusan?.length) {
        console.error("Data jurusan kosong");
        return;
    }

    // Inisialisasi chart
    try {
        if (chartJurusan) chartJurusan.destroy();
        
        chartJurusan = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: dashboardData.value.labels_jurusan,
                datasets: [{
                    data: dashboardData.value.values_jurusan,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    } catch (error) {
        console.error("Chart error:", error);
    }
};
          
        // Load data from server
const loadData = () => {
    if (!window.location.href.includes('x=dashboard')) {
    console.log('Skipping load for non-dashboard page');
    return;
}
    console.log("Data dari PHP:", window.serverData);

    // Validasi serverData
    if (!window.serverData) {
        console.error('Error: window.serverData is undefined');
        return;
    }


    // Transformasi data dengan validasi ketat
    dashboardData.value = {
        totalMahasiswa: parseInt(window.serverData.totalMahasiswa) || 0,
        pendaftarBulanIni: parseInt(window.serverData.pendaftarBulanIni) || 0,
        labels_jurusan: Array.isArray(window.serverData.labels_jurusan) 
            ? [...window.serverData.labels_jurusan] 
            : [],
        values_jurusan: Array.isArray(window.serverData.values_jurusan) 
            ? window.serverData.values_jurusan.map(v => Number(v) || 0) // Lebih robust
            : [],
        recentRegistrations: Array.isArray(window.serverData.recentRegistrations) 
            ? [...window.serverData.recentRegistrations] 
            : []
    };

     console.log("Data di Vue:", dashboardData.value);

    // Update UI setelah data siap
    nextTick(() => {
        try {
            $animateCounters([
            dashboardData.value.totalMahasiswa, 
            dashboardData.value.pendaftarBulanIni
        ]);
            initCharts();
        } catch (error) {
            console.error('Error in post-load operations:', error);
        }
    });
};
        // Initialize
        onMounted(() => {
            loadData();
            window.addEventListener('resize', () => {
                isMobile.value = $checkMobile();
                resizeCharts();
            });
            
        });

        watch(() => [
            dashboardData.value.labels_jurusan,
            dashboardData.value.values_jurusan
        ], initCharts, { deep: true });

        return {
            sidebarCollapsed,
            isMobile,
            dashboardData,
            departmentChartRef,
            toggleSidebar,
            viewStudent: (npm) => console.log('View student details:', npm),
            handleExportChart: (type) => {
                const canvas = departmentChartRef.value;
                if (!canvas) return;
                
                const link = document.createElement('a');
                link.download = `${type}-${new Date().toISOString().slice(0,10)}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            },
            formatNumber: $formatNumber,
            formatDate: $formatDate,
        };
    }
}).mount('#app');