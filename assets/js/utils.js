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

function animateCounters() {
    console.log('Starting counter animation...');
    const counters = document.querySelectorAll('.counter');
    
    if (!counters.length) {
        console.warn('No counter elements found');
        return;
    }

    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/,/g, '')) || 0;
        console.log(`Animating counter from 0 to ${target}`);
        
        let current = 0;
        const increment = Math.max(1, Math.ceil(target / 100));
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                clearInterval(timer);
                current = target;
            }
            counter.textContent = formatNumber(current);
        }, 10);
    });
}

// Profile picture utilities
function uploadProfilePicture(formData) {
    return fetch('upload_profile.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Upload error:', error);
        return { success: false, message: 'Network error' };
    });
}

function removeProfilePicture() {
    return fetch('upload_profile.php', {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Remove error:', error);
        return { success: false, message: 'Network error' };
    });
}

function previewProfilePicture(input, previewElement) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (previewElement.tagName === 'IMG') {
                previewElement.src = e.target.result;
            } else {
                // Jika previewElement adalah div
                previewElement.innerHTML = `<img src="${e.target.result}" class="rounded-circle" style="width:150px;height:150px;object-fit:cover;">`;
            }
        };
        reader.readAsDataURL(file);
    }
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
            uploadProfilePicture,
            removeProfilePicture,
            previewProfilePicture
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
        uploadProfilePicture,
        removeProfilePicture,
        previewProfilePicture
    };
}else {
    window.$dashboard.utils.uploadProfilePicture = uploadProfilePicture;
    window.$dashboard.utils.removeProfilePicture = removeProfilePicture;
    window.$dashboard.utils.previewProfilePicture = previewProfilePicture;
}

console.log('Dashboard utilities initialized');