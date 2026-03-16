/**
 * BeautyOS - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 10
                ? '0 2px 20px rgba(0,0,0,0.06)'
                : 'none';
        });
    }

    // Flash message auto-dismiss
    document.querySelectorAll('.flash').forEach(flash => {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(() => flash.remove(), 300);
        }, 5000);
    });

    // Favorite buttons
    document.querySelectorAll('.card-favorite').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const icon = btn.querySelector('i');
            const businessId = btn.dataset.businessId;

            if (icon.classList.contains('far')) {
                icon.classList.replace('far', 'fas');
                btn.classList.add('active');
            } else {
                icon.classList.replace('fas', 'far');
                btn.classList.remove('active');
            }

            if (businessId) {
                try {
                    await fetch('/api/favorite.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ business_id: businessId })
                    });
                } catch (err) {
                    // silently fail
                }
            }
        });
    });
});

/**
 * Initialize Leaflet map
 */
function initMap(elementId, businesses = [], center = [51.1657, 10.4515], zoom = 6) {
    const map = L.map(elementId).setView(center, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    const pinkIcon = L.divIcon({
        className: 'custom-marker',
        html: '<div style="background:var(--primary);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(232,160,191,0.5);border:3px solid white;"><i class="fas fa-store" style="color:white;font-size:12px;"></i></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
    });

    businesses.forEach(biz => {
        if (biz.latitude && biz.longitude) {
            const marker = L.marker([biz.latitude, biz.longitude], { icon: pinkIcon }).addTo(map);
            marker.bindPopup(`
                <div style="min-width:200px;">
                    <strong style="font-size:14px;">${biz.name}</strong><br>
                    <span style="color:#757575;font-size:12px;"><i class="fas fa-map-marker-alt"></i> ${biz.city || ''}</span><br>
                    ${biz.avg_rating ? `<span style="color:#ffa726;">&#9733;</span> ${parseFloat(biz.avg_rating).toFixed(1)}` : ''}
                    <br><a href="/business.php?slug=${biz.slug}" style="color:#e8a0bf;font-weight:500;font-size:13px;">Profil ansehen &rarr;</a>
                </div>
            `);
        }
    });

    return map;
}

/**
 * View toggle (grid/map)
 */
function toggleView(view) {
    const gridView = document.getElementById('gridView');
    const mapView = document.getElementById('mapView');
    const buttons = document.querySelectorAll('.view-toggle button');

    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    if (view === 'map') {
        gridView.style.display = 'none';
        mapView.style.display = 'block';
        if (!window.mapInitialized) {
            window.beautyMap = initMap('map', window.businessesData || []);
            window.mapInitialized = true;
        }
        window.beautyMap.invalidateSize();
    } else {
        gridView.style.display = 'block';
        mapView.style.display = 'none';
    }
}

/**
 * Booking: select time slot
 */
function selectTimeSlot(el) {
    if (el.classList.contains('unavailable')) return;
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    const input = document.getElementById('selectedTime');
    if (input) input.value = el.dataset.time;
}

/**
 * Booking: select service
 */
function selectService(el) {
    document.querySelectorAll('.service-list-item').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    el.style.background = 'var(--primary-50)';
    el.style.borderRadius = 'var(--radius-sm)';
    el.style.padding = '14px 8px';
    const input = document.getElementById('selectedService');
    if (input) input.value = el.dataset.serviceId;
}
