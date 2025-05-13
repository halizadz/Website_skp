/**
 * Chart components and functions
 */



// Initialize department distribution chart
function initDepartmentChart(ctx, labels, values, totalStudents) {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    'rgba(108, 93, 211, 0.7)',
                    'rgba(132, 121, 225, 0.7)',
                    'rgba(159, 150, 234, 0.7)',
                    'rgba(186, 182, 242, 0.7)',
                    'rgba(213, 213, 250, 0.7)'
                ],
                borderColor: 'rgba(255, 255, 255, 0.8)',
                borderWidth: 1
            }]
        },
        options: getDepartmentChartOptions(totalStudents)
    });
}

// Department chart options
function getDepartmentChartOptions(totalStudents) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    font: {
                        size: 12,
                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                    },
                    padding: 20
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.85)',
                titleFont: {
                    size: 14,
                    weight: 'bold',
                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                },
                bodyFont: {
                    size: 12,
                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                },
                callbacks: {
                    label: function(context) {
                        const value = context.raw;
                        const percentage = totalStudents > 0 ? Math.round((value / totalStudents) * 100) : 0;
                        return ` ${value} mahasiswa (${percentage}%)`;
                    }
                },
                displayColors: true,
                padding: 10,
                cornerRadius: 4
            }
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        }
    };
}

// Expose functions to global namespace using consistent structure
if (!window.$dashboard) {
    window.$dashboard = {
        charts: {
            initMonthlyChart,
            getMonthlyChartOptions,
        }
    };
} else if (!window.$dashboard.charts) {
    window.$dashboard.charts = {
        initMonthlyChart,
        getMonthlyChartOptions,
    };
}
console.log('Dashboard chart utilities initialized');