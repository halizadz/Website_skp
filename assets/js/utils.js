/**
 * Utility functions for dashboard
 */

// Check if device is mobile
function checkMobile() {
    return window.innerWidth < 992;
}

// Format number with thousands separator
function formatNumber(num) {
    try {
        return new Intl.NumberFormat('id-ID').format(num);
    } catch (e) {
        console.error('Format error:', e);
        return num?.toString() || '0';
    }
}

// Format date to Indonesian format
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';
        
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    } catch (e) {
        console.error("Error formatting date:", e);
        return 'Invalid date';
    }
}

// Export chart as PNG image
function exportChart(canvasElement, filename) {
    if (!canvasElement) return;
    
    const imageLink = document.createElement('a');
    imageLink.download = `${filename}.png`;
    imageLink.href = canvasElement.toDataURL('image/png');
    imageLink.click();
}

function animateCounters(targetValues = []) {
    const counters = document.querySelectorAll('.stat-number.counter');
    
    if (!counters.length || counters.length !== targetValues.length) {
        console.warn('Counter elements not found or count mismatch');
        return;
    }

    counters.forEach((counter, index) => {
        const target = targetValues[index] || 0;
        let current = 0;
        const increment = Math.max(1, Math.ceil(target / 50));
        
        // Reset counter untuk animasi bersih
        counter.textContent = '0';
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                clearInterval(timer);
                current = target;
            }
            counter.textContent = formatNumber(current);
        }, 20);
    });
}

// Expose to global namespace
if (!window.$dashboard) {
    window.$dashboard = {
        utils: {
            checkMobile,
            formatNumber,
            formatDate,
            exportChart,
            animateCounters,
        },
        charts: {}
    };
} else if (!window.$dashboard.utils) {
    window.$dashboard.utils = {
        checkMobile,
        formatNumber,
        formatDate,
        exportChart,
        animateCounters,
    };
}

console.log('Dashboard utilities initialized');