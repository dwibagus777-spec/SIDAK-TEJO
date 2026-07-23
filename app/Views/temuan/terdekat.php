<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Temuan Terdekat<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Temuan Lokasi Terdekat<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Temuan</a></li>
<li class="breadcrumb-item active">Temuan Terdekat</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<style>
    /* Dark Theme Map Container with Glassmorphism Border */
    #map-terdekat {
        height: 500px;
        width: 100%;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    .panel-terdekat {
        background: #0f172a;
        color: #f3f4f6;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        height: 500px;
        display: flex;
        flex-direction: column;
    }

    .panel-header-terdekat {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 16px;
        border-top-left-radius: 11px;
        border-top-right-radius: 11px;
    }

    .list-temuan-container {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
    }

    .temuan-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .temuan-card:hover {
        background: rgba(59, 130, 246, 0.08);
        border-color: rgba(59, 130, 246, 0.4);
        transform: translateY(-2px);
    }

    .temuan-card.selected-card {
        background: rgba(59, 130, 246, 0.15) !important;
        border-color: #3b82f6 !important;
    }

    .badge-distance {
        background: #2563eb;
        color: #ffffff;
        font-weight: bold;
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 9999px;
    }

    /* Pulse animation for current location pulse */
    .gps-pulse-marker {
        background: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
        border-radius: 50%;
        animation: pulse 1.6s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
        }
        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
        }
    }

    /* Floating Voice Command Button Styles */
    #btn-voice-mic.listening {
        background: #dc3545 !important;
        border-color: rgba(255,255,255,0.3) !important;
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7) !important;
        animation: mic-pulse 1.4s infinite !important;
    }

    @keyframes mic-pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }
        70% {
            transform: scale(1.1);
            box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }
</style>

<div class="row mb-3">
    <!-- Kontrol Koordinat & Radius -->
    <div class="col-12">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-primary"><i class="fas fa-compass mr-1"></i> Penentuan Lokasi Pencarian</h3>
            </div>
            <div class="card-body py-3">
                <div class="row">
                    <!-- Latitude -->
                    <div class="col-md-3 form-group mb-2">
                        <label class="small font-weight-bold">Latitude Lokasi</label>
                        <input type="text" id="input-lat" class="form-control form-control-sm font-monospace" value="-7.456865" readonly>
                    </div>
                    <!-- Longitude -->
                    <div class="col-md-3 form-group mb-2">
                        <label class="small font-weight-bold">Longitude Lokasi</label>
                        <input type="text" id="input-lng" class="form-control form-control-sm font-monospace" value="112.716164" readonly>
                    </div>
                    <!-- Radius -->
                    <div class="col-md-3 form-group mb-2">
                        <label class="small font-weight-bold">Radius Jangkauan</label>
                        <select id="input-radius" class="form-control form-control-sm select2">
                            <option value="100">100 Meter</option>
                            <option value="500" selected>500 Meter</option>
                            <option value="1000">1 Kilometer</option>
                            <option value="2000">2 Kilometer</option>
                            <option value="5000">5 Kilometer</option>
                            <option value="10000">10 Kilometer</option>
                        </select>
                    </div>
                    <!-- GPS Trigger -->
                    <div class="col-md-3 form-group mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-primary btn-sm btn-block font-weight-bold" id="btn-gps">
                            <i class="fas fa-location-crosshairs mr-1"></i> Gunakan GPS Saya
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- PETA LEAFLET -->
    <div class="col-lg-8 mb-3">
        <div class="card card-dark card-outline">
            <div class="card-body p-2">
                <div id="map-terdekat"></div>
                <div class="small text-muted mt-2 text-center">
                    <i class="fas fa-circle-info mr-1"></i> <strong>Tips:</strong> Klik bebas di peta atau geser penanda biru untuk menentukan lokasi pencarian secara manual.
                </div>
            </div>
        </div>
    </div>

    <!-- DAFTAR TEMUAN TERDEKAT -->
    <div class="col-lg-4 mb-3">
        <div class="panel-terdekat shadow-sm">
            <div class="panel-header-terdekat d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-list mr-1"></i> Hasil Temuan Terdekat</h6>
                <span class="badge bg-primary" id="count-results">0 Temuan</span>
            </div>
            
            <div class="list-temuan-container" id="list-temuan-terdekat">
                <!-- Dynamic cards will be appended here -->
                <div class="text-center py-5 text-muted" id="initial-state">
                    <i class="fas fa-location-dot fa-3x mb-3 text-secondary animate__animated animate__pulse animate__infinite"></i>
                    <p>Klik peta atau tekan "Gunakan GPS Saya" untuk mencari temuan terdekat.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<script>
    let map;
    let pickerMarker;
    let markersGroup = L.layerGroup();
    let searchCircle;

    $(function() {
        // Init Select2
        if ($('.select2').length) {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        }

        // 1. INITIALIZE MAP
        const defaultLat = -7.456865;
        const defaultLng = 112.716164;

        map = L.map('map-terdekat').setView([defaultLat, defaultLng], 14);

        // Light Theme Tile Layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        markersGroup.addTo(map);

        // Picker Marker (Lokasi Pilihan)
        const blueIcon = L.icon({
            iconUrl: '<?= base_url('plugins/images/marker-icon-2x-blue.png') ?>',
            shadowUrl: '<?= base_url('plugins/images/marker-shadow.png') ?>',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        pickerMarker = L.marker([defaultLat, defaultLng], {
            icon: blueIcon,
            draggable: true
        }).addTo(map);

        pickerMarker.bindPopup('<b>Lokasi Pencarian</b><br>Geser saya untuk mengubah lokasi').openPopup();

        // Search Circle
        searchCircle = L.circle([defaultLat, defaultLng], {
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.15,
            radius: parseInt($('#input-radius').val())
        }).addTo(map);

        // 2. EVENT LISTENERS
        // Geser marker
        pickerMarker.on('dragend', function(e) {
            const position = pickerMarker.getLatLng();
            updateLocationInputs(position.lat, position.lng);
        });

        // Klik peta
        map.on('click', function(e) {
            pickerMarker.setLatLng(e.latlng);
            updateLocationInputs(e.latlng.lat, e.latlng.lng);
        });

        // Radius berubah
        $('#input-radius').change(function() {
            const radius = parseInt($(this).val());
            searchCircle.setRadius(radius);
            searchNearbyFindings();
        });

        // Tombol GPS
        $('#btn-gps').click(function() {
            if (navigator.geolocation) {
                Swal.fire({
                    title: 'Mencari Lokasi GPS...',
                    text: 'Harap izinkan akses lokasi di peramban Anda.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                navigator.geolocation.getCurrentPosition(function(position) {
                    Swal.close();
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    pickerMarker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 15);
                    updateLocationInputs(lat, lng);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Lokasi Ditemukan',
                        text: 'Koordinat GPS Anda berhasil diterapkan.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }, function(error) {
                    Swal.close();
                    const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                    const msg = isHttp 
                        ? 'Fitur GPS membutuhkan koneksi HTTPS. Peramban memblokir akses lokasi pada koneksi HTTP (bukan HTTPS). Harap pasang SSL/HTTPS pada server.'
                        : 'Tidak dapat mendapatkan lokasi GPS. Harap periksa izin browser Anda.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Akses Gagal',
                        text: msg
                    });
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Didukung',
                    text: 'Geolokasi tidak didukung oleh browser Anda.'
                });
            }
        });

        // 4. VOICE COMMANDS LISTENER (Dispatched from global mic in admin layout)
        window.addEventListener('appVoiceCommand', function(e) {
            const speechResult = e.detail ? e.detail.transcript : '';
            if (!speechResult) return;
            
            console.log('Voice Command Received in Terdekat page:', speechResult);
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });

            if (speechResult.includes('gps') || speechResult.includes('lokasi saya') || speechResult.includes('koordinat saya')) {
                e.preventDefault();
                Toast.fire({
                    icon: 'success',
                    title: 'Melacak GPS...'
                });
                $('#btn-gps').click();
            }
            else if (speechResult.includes('radius') || speechResult.includes('jarak')) {
                let numMatch = speechResult.match(/\d+/);
                if (numMatch) {
                    e.preventDefault();
                    let radVal = parseInt(numMatch[0]);
                    if (speechResult.includes('kilometer') || speechResult.includes(' kilo') || speechResult.includes(' km')) {
                        radVal = radVal * 1000;
                    }
                    
                    const selectElement = $('#input-radius');
                    let bestOptionValue = 500;
                    let minDiff = Infinity;
                    
                    selectElement.find('option').each(function() {
                        const optVal = parseInt($(this).val());
                        const diff = Math.abs(optVal - radVal);
                        if (diff < minDiff) {
                            minDiff = diff;
                            bestOptionValue = optVal;
                        }
                    });
                    
                    selectElement.val(bestOptionValue).trigger('change');
                    
                    Toast.fire({
                        icon: 'success',
                        title: 'Mengubah radius ke ' + selectElement.find('option:selected').text()
                    });
                }
            }
            else if (speechResult.includes('filter') || speechResult.includes('saring')) {
                const cleanKeyword = speechResult.replace('filter', '').replace('saring', '').trim();
                if (cleanKeyword && typeof filterListByKeyword === 'function') {
                    e.preventDefault();
                    Toast.fire({
                        icon: 'success',
                        title: 'Menyaring temuan: "' + cleanKeyword + '"'
                    });
                    filterListByKeyword(cleanKeyword);
                }
            }
            else if (speechResult.includes('reset') || speechResult.includes('ulang')) {
                e.preventDefault();
                Toast.fire({
                    icon: 'info',
                    title: 'Mereset pencarian...'
                });
                $('#input-radius').val('500').trigger('change');
                $('.temuan-card').removeClass('d-none');
                searchNearbyFindings();
            }
        });

        // Jalankan pencarian pertama (bisa auto GPS)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('gps') === 'true') {
            $('#btn-gps').click();
        } else {
            updateLocationInputs(defaultLat, defaultLng);
        }
    });

    function updateLocationInputs(lat, lng) {
        $('#input-lat').val(lat.toFixed(8));
        $('#input-lng').val(lng.toFixed(8));
        searchCircle.setLatLng([lat, lng]);
        
        searchNearbyFindings();
    }

    function filterListByKeyword(keyword) {
        let countVisible = 0;
        $('.temuan-card').each(function() {
            const cardText = $(this).text().toLowerCase();
            if (cardText.includes(keyword)) {
                $(this).removeClass('d-none');
                countVisible++;
            } else {
                $(this).addClass('d-none');
            }
        });
        $('#count-results').text(countVisible + ' Temuan (Terfilter)');
    }

    // 3. SEARCH AJAX FINDINGS
    function searchNearbyFindings() {
        const lat = parseFloat($('#input-lat').val());
        const lng = parseFloat($('#input-lng').val());
        const radius = parseInt($('#input-radius').val());

        $('#list-temuan-terdekat').html(
            '<div class="text-center py-5 text-muted">' +
            '<div class="spinner-border text-primary mb-3" role="status"></div>' +
            '<p>Mencari temuan terdekat...</p>' +
            '</div>'
        );

        $.ajax({
            url: '<?= site_url('temuan/ajax-terdekat') ?>',
            type: 'GET',
            data: { latitude: lat, longitude: lng, radius: radius },
            dataType: 'JSON',
            success: function(data) {
                // Clear existing markers
                markersGroup.clearLayers();
                $('#list-temuan-terdekat').empty();

                const count = data.length;
                $('#count-results').text(count + ' Temuan');

                if (count === 0) {
                    $('#list-temuan-terdekat').html(
                        '<div class="text-center py-5 text-muted">' +
                        '<i class="fas fa-circle-exclamation fa-3x mb-3 text-secondary"></i>' +
                        '<p class="mb-0">Tidak ada temuan dalam radius ' + (radius >= 1000 ? (radius/1000) + ' km' : radius + ' m') + ' dari lokasi ini.</p>' +
                        '</div>'
                    );
                    return;
                }

                // Green/Yellow/Red Icons based on Priority
                const priorityIcons = {
                    'TINGGI': L.icon({
                        iconUrl: '<?= base_url('plugins/images/marker-icon-2x-red.png') ?>',
                        shadowUrl: '<?= base_url('plugins/images/marker-shadow.png') ?>',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    }),
                    'SEDANG': L.icon({
                        iconUrl: '<?= base_url('plugins/images/marker-icon-2x-gold.png') ?>',
                        shadowUrl: '<?= base_url('plugins/images/marker-shadow.png') ?>',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    }),
                    'RENDAH': L.icon({
                        iconUrl: '<?= base_url('plugins/images/marker-icon-2x-green.png') ?>',
                        shadowUrl: '<?= base_url('plugins/images/marker-shadow.png') ?>',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                };

                // Add findings to map & list
                data.forEach(function(item, index) {
                    // 1. Add marker to map
                    const icon = priorityIcons[item.prioritas] || priorityIcons['RENDAH'];
                    const marker = L.marker([parseFloat(item.latitude), parseFloat(item.longitude)], { icon: icon });
                    
                    const popupContent = 
                        '<div class="text-center" style="font-size:0.85rem;color:#1e293b;">' +
                        '  <strong>No Temuan: ' + item.nomor_temuan + '</strong><br>' +
                        '  <span>Prioritas: <span class="badge bg-' + (item.prioritas === 'TINGGI' ? 'danger' : (item.prioritas === 'SEDANG' ? 'warning' : 'success')) + '">' + item.prioritas + '</span></span><br>' +
                        '  <span class="d-block mt-1">Jarak: <b>' + item.distance_text + '</b></span>' +
                        '  <a href="<?= site_url('temuan/detail/') ?>' + item.id + '" class="btn btn-xs btn-primary text-white font-weight-bold d-block mt-2" target="_blank">Lihat Detail</a>' +
                        '</div>';
                        
                    marker.bindPopup(popupContent);
                    markersGroup.addLayer(marker);

                    // Map markers to array elements for reference
                    item.marker = marker;

                    // 2. Add card to list
                    const borderLeftColor = item.prioritas === 'TINGGI' ? '#dc3545' : (item.prioritas === 'SEDANG' ? '#ffc107' : '#28a745');
                    const badgeClass = item.prioritas === 'TINGGI' ? 'danger' : (item.prioritas === 'SEDANG' ? 'warning' : 'success');
                    
                    const cardHtml = 
                        '<div class="temuan-card" id="card-' + item.id + '" style="border-left: 4px solid ' + borderLeftColor + ';">' +
                        '  <div class="d-flex justify-content-between align-items-start mb-1">' +
                        '    <span class="font-weight-bold text-white small" style="letter-spacing:0.5px;">' + item.nomor_temuan + '</span>' +
                        '    <span class="badge-distance"><i class="fas fa-route mr-1"></i>' + item.distance_text + '</span>' +
                        '  </div>' +
                        '  <div class="small text-muted mb-2">' + (item.nama_ulp || '-') + ' &bull; ' + (item.nama_penyulang || '-') + '</div>' +
                        '  <div class="text-white font-weight-bold small mb-2">' + item.jenis_temuan + '</div>' +
                        '  <p class="small text-secondary mb-2" style="font-size:0.75rem; line-height:1.35; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + (item.detail_temuan || '-') + '</p>' +
                        '  <div class="d-flex align-items-center justify-content-between">' +
                        '    <span class="badge bg-' + badgeClass + ' text-xs">' + item.prioritas + '</span>' +
                        '    <div class="d-flex" style="gap:4px;">' +
                        '      <button class="btn btn-xs btn-secondary text-white font-weight-bold btn-zoom-to" data-index="' + index + '"><i class="fas fa-search-location mr-1"></i>Zoom</button>' +
                        '      <a href="<?= site_url('temuan/detail/') ?>' + item.id + '" class="btn btn-xs btn-primary text-white font-weight-bold" target="_blank"><i class="fas fa-eye mr-1"></i>Detail</a>' +
                        '    </div>' +
                        '  </div>' +
                        '</div>';
                        
                    $('#list-temuan-terdekat').append(cardHtml);
                });

                // Zoom finding click handler
                $('.btn-zoom-to').click(function(e) {
                    e.stopPropagation();
                    const idx = $(this).data('index');
                    const item = data[idx];
                    
                    $('.temuan-card').removeClass('selected-card');
                    $('#card-' + item.id).addClass('selected-card');
                    
                    map.setView([parseFloat(item.latitude), parseFloat(item.longitude)], 17);
                    item.marker.openPopup();
                });

                // Card click zooms to marker
                $('.temuan-card').click(function() {
                    $(this).find('.btn-zoom-to').click();
                });
            },
            error: function() {
                $('#list-temuan-terdekat').html(
                    '<div class="text-center py-5 text-danger">' +
                    '<i class="fas fa-circle-exclamation fa-3x mb-3"></i>' +
                    '<p class="mb-0 font-weight-bold">Gagal mengambil data dari server.</p>' +
                    '</div>'
                );
            }
        });
    }
</script>
<?= $this->endSection() ?>
