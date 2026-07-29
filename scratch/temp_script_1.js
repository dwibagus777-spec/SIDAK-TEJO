
    $(function() {
        // --- 1. CASCADING DROPDOWNS ---
        const oldPenyulangId = "dummyVar";
        const oldSectionId = "dummyVar";

        function refreshSelect2($element) {
            if ($element.hasClass('select2-hidden-accessible')) {
                $element.trigger('change.select2');
            } else {
                $element.trigger('change');
            }
        }

        function loadPenyulang(ulpId, callback) {
            const $penyulang = $('#penyulang_id');
            const $section = $('#section_id');

            console.log("[SIDAK TEJO] ULP berubah:", ulpId);

            if (!ulpId) {
                $penyulang.html('<option value="">-- Pilih ULP Dahulu --</option>');
                $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                refreshSelect2($penyulang);
                refreshSelect2($section);
                return;
            }

            $penyulang.html('<option value="">Sedang memuat...</option>');
            refreshSelect2($penyulang);

            const requestUrl = "dummyVar/" + ulpId;
            console.log("[SIDAK TEJO] Request AJAX Penyulang URL:", requestUrl);

            $.ajax({
                url: requestUrl,
                type: "GET",
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(data) {
                    console.log("[SIDAK TEJO] Response JSON Penyulang:", data);
                    const totalPenyulang = Array.isArray(data) ? data.length : 0;
                    console.log("[SIDAK TEJO] Jumlah Penyulang:", totalPenyulang);

                    let html = '<option value="">-- Pilih Penyulang --</option>';
                    if (totalPenyulang > 0) {
                        data.forEach(function(item) {
                            html += `<option value="${item.id}">${item.nama_penyulang}</option>`;
                        });
                    } else {
                        html = '<option value="">-- Tidak ada penyulang aktif --</option>';
                    }
                    $penyulang.html(html);
                    refreshSelect2($penyulang);

                    $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                    refreshSelect2($section);

                    if (callback) callback();
                },
                error: function(xhr, status, err) {
                    console.error("[SIDAK TEJO] Gagal AJAX Penyulang!", {
                        url: requestUrl,
                        statusCode: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: err
                    });
                    $penyulang.html('<option value="">Gagal memuat penyulang (Status: ' + xhr.status + ')</option>');
                    refreshSelect2($penyulang);
                }
            });
        }

        function loadSection(penyulangId, callback) {
            const $section = $('#section_id');

            console.log("[SIDAK TEJO] Penyulang berubah:", penyulangId);

            if (!penyulangId) {
                $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                refreshSelect2($section);
                return;
            }

            $section.html('<option value="">Sedang memuat...</option>');
            refreshSelect2($section);

            const requestUrl = "dummyVar/" + penyulangId;
            console.log("[SIDAK TEJO] Request AJAX Section URL:", requestUrl);

            $.ajax({
                url: requestUrl,
                type: "GET",
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(data) {
                    console.log("[SIDAK TEJO] Response JSON Section:", data);
                    const totalSection = Array.isArray(data) ? data.length : 0;
                    console.log("[SIDAK TEJO] Jumlah Section:", totalSection);

                    let html = '<option value="">-- Pilih Section --</option>';
                    if (totalSection > 0) {
                        data.forEach(function(item) {
                            html += `<option value="${item.id}">${item.nama_section}</option>`;
                        });
                    } else {
                        html = '<option value="">-- Tidak ada section aktif --</option>';
                    }
                    $section.html(html);
                    refreshSelect2($section);

                    if (callback) callback();
                },
                error: function(xhr, status, err) {
                    console.error("[SIDAK TEJO] Gagal AJAX Section!", {
                        url: requestUrl,
                        statusCode: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: err
                    });
                    $section.html('<option value="">Gagal memuat section (Status: ' + xhr.status + ')</option>');
                    refreshSelect2($section);
                }
            });
        }

        // Dropdown triggers
        $('#ulp_id').on('change', function() {
            loadPenyulang($(this).val());
        });

        $('#penyulang_id').on('change', function() {
            loadSection($(this).val());
        });

        // Restore old input cascade (if validation fails or pre-selected)
        const initialUlpId = $('#ulp_id').val();
        if (initialUlpId) {
            loadPenyulang(initialUlpId, function() {
                if (oldPenyulangId) {
                    $('#penyulang_id').val(oldPenyulangId);
                    refreshSelect2($('#penyulang_id'));
                    loadSection(oldPenyulangId, function() {
                        if (oldSectionId) {
                            $('#section_id').val(oldSectionId);
                            refreshSelect2($('#section_id'));
                        }
                    });
                }
            });
        }

        // --- 2. MULTI-PHOTO UPLOAD PREVIEW & COMPRESSION ---
        function compressSingleImage(file, maxWidth = 1600, quality = 0.8) {
            return new Promise((resolve) => {
                if (!file || !file.type.startsWith('image/') || file.size <= 400 * 1024) {
                    resolve(file);
                    return;
                }
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = (e) => {
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = () => {
                        let w = img.width, h = img.height;
                        const maxDim = maxWidth;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
                            else { w = Math.round((w * maxDim) / h); h = maxDim; }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        canvas.toBlob((blob) => {
                            if (blob && blob.size < file.size) {
                                const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', quality);
                    };
                    img.onerror = () => resolve(file);
                };
                reader.onerror = () => resolve(file);
            });
        }

        // Store for accumulating files from both Galeri & Kamera
        let createPhotoStore = new DataTransfer();

        $('#btn-pick-gallery').click(function() {
            $('#foto').trigger('click');
        });

        $('#btn-pick-camera').click(function() {
            $('#foto_camera').trigger('click');
        });

        function renderPhotoPreviews() {
            const container = $('#preview-container');
            container.empty();
            const count = createPhotoStore.files.length;

            if (count > 0) {
                $('#file-selection-info').html('<span class="badge bg-success text-white p-2" style="font-size:12px;"><i class="fas fa-check-circle mr-1"></i> ' + count + ' foto dipilih dan siap diunggah</span>');
            } else {
                $('#file-selection-info').html('<i class="fas fa-info-circle mr-1"></i> Format berkas: JPG, JPEG, PNG, WEBP. Bisa memilih dari Galeri atau ambil langsung via Kamera.');
            }

            // Sync store files to hidden input #foto
            const fileInput = document.getElementById('foto');
            if (fileInput) {
                fileInput.files = createPhotoStore.files;
            }

            for (let i = 0; i < count; i++) {
                const file = createPhotoStore.files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const html = `
                        <div class="col-md-3 col-6 mb-3 position-relative animate__animated animate__fadeIn">
                            <div class="img-thumbnail bg-dark p-1" style="border-color: #3d3d3d; border-radius: 8px; overflow: hidden; height: 110px; display: flex; align-items: center; justify-content: center; position: relative;">
                                <img src="${e.target.result}" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                <button type="button" class="btn btn-danger btn-sm btn-remove-item position-absolute" data-index="${i}" style="top: 4px; right: 4px; border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 24px; font-size: 11px;" title="Hapus foto ini">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    container.append(html);
                };
                reader.readAsDataURL(file);
            }
        }

        function handleIncomingFiles(incomingFiles) {
            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            for (let i = 0; i < incomingFiles.length; i++) {
                const f = incomingFiles[i];
                if (!allowed.includes(f.type)) {
                    Toast.fire({ icon: 'error', title: 'Format berkas "' + f.name + '" tidak diizinkan!' });
                    continue;
                }
                if (createPhotoStore.files.length >= 10) {
                    Toast.fire({ icon: 'warning', title: 'Maksimal upload 10 foto.' });
                    break;
                }
                createPhotoStore.items.add(f);
            }
            renderPhotoPreviews();
        }

        $('#foto, #foto_camera').change(function() {
            if (this.files && this.files.length > 0) {
                handleIncomingFiles(this.files);
                if (this.id === 'foto_camera') {
                    this.value = '';
                }
            }
        });

        $(document).on('click', '.btn-remove-item', function() {
            const idx = $(this).data('index');
            const newStore = new DataTransfer();
            for (let i = 0; i < createPhotoStore.files.length; i++) {
                if (i !== idx) {
                    newStore.items.add(createPhotoStore.files[i]);
                }
            }
            createPhotoStore = newStore;
            renderPhotoPreviews();
        });

        // Ensure photo selection validation and material JSON sync before submit
        $('#form-create-temuan').submit(function(e) {
            // Sync files from createPhotoStore to input #foto
            const fileInput = document.getElementById('foto');
            if (fileInput && createPhotoStore.files.length > 0) {
                fileInput.files = createPhotoStore.files;
            }

            if (createPhotoStore.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Foto Belum Dipilih',
                    text: 'Harap unggah minimal 1 foto temuan sebelum menyimpan!',
                    confirmButtonColor: '#005eb8'
                });
                return false;
            }

            // Sync material list to hidden field
            let materialItems = [];
            $('.material-item-row').each(function() {
                const nama = $(this).find('.input-nama-material').val().trim();
                const qty = $(this).find('.input-jumlah-material').val().trim();
                if (nama !== '') {
                    materialItems.push(qty ? `- ${qty} ${nama}` : `- ${nama}`);
                }
            });
            if (materialItems.length > 0) {
                $('#material-hidden-field').val(materialItems.join("\n"));
            } else {
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            }

            const btnSubmit = $('#btn-submit');
            btnSubmit.html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan data...');
            // Allow native form submission to proceed with multipart payload intact
            return true;
        });

        // --- 3. GEOLOCATION & LEAFLET SELECTOR MAP ---
        if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
            L.Icon.Default.imagePath = 'dummyVar/';
        }

        const defaultLat = -7.4478;
        const defaultLng = 112.7183;

        // Initialize Selector Map
        const map = L.map('selector-map').setView([defaultLat, defaultLng], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20
        }).addTo(map);

        setTimeout(function() {
            if (map) {
                map.invalidateSize();
            }
        }, 300);

        const customIcon = L.icon({
            iconUrl: 'dummyVar',
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -38]
        });

        // Marker (draggable)
        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true,
            icon: customIcon
        }).addTo(map);
        marker.bindPopup('<b>Geser pin untuk menetapkan lokasi</b>').openPopup();

        function updateCoordinates(lat, lng) {
            $('#latitude').val(lat.toFixed(8));
            $('#longitude').val(lng.toFixed(8));
        }

        // Trigger on marker drag end
        marker.on('dragend', function(e) {
            const position = marker.getLatLng();
            updateCoordinates(position.lat, position.lng);
        });

        // Trigger on map click
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoordinates(e.latlng.lat, e.latlng.lng);
        });

        // Geolocation trigger
        $('#btn-geolocation').click(function() {
            if (navigator.geolocation) {
                $('#btn-geolocation').html('<i class="fas fa-spinner fa-spin mr-1"></i> Mendapatkan Lokasi...');
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng);
                        
                        $('#btn-geolocation').html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        Toast.fire({
                            icon: 'success',
                            title: 'Lokasi Anda berhasil didapatkan!'
                        });
                    },
                    function(error) {
                        $('#btn-geolocation').html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        let errMsg = 'Gagal mendapatkan lokasi.';
                        if (error.code === error.PERMISSION_DENIED) {
                            const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                            errMsg = isHttp
                                ? 'Akses lokasi diblokir peramban pada koneksi HTTP (bukan HTTPS). Harap pasang SSL/HTTPS pada server.'
                                : 'Izin lokasi ditolak oleh pengguna.';
                        }
                        Toast.fire({
                            icon: 'error',
                            title: errMsg
                        });
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            } else {
                Toast.fire({
                    icon: 'error',
                    title: 'Browser Anda tidak mendukung HTML5 Geolocation.'
                });
            }
        });

        // Manual coordinate input: "Sinkronkan ke Peta" button
        $('#btn-sync-map').click(function() {
            const lat = parseFloat($('#latitude').val());
            const lng = parseFloat($('#longitude').val());
            if (isNaN(lat) || isNaN(lng)) {
                Toast.fire({ icon: 'warning', title: 'Masukkan Latitude dan Longitude yang valid terlebih dahulu.' });
                return;
            }
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                Toast.fire({ icon: 'error', title: 'Nilai koordinat di luar rentang yang valid.' });
                return;
            }
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            Toast.fire({ icon: 'success', title: 'Pin peta diperbarui ke koordinat yang dimasukkan.' });
        });

        // Auto-sync when user presses Enter on lat/lng fields
        $('#latitude, #longitude').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#btn-sync-map').trigger('click');
            }
        });
        // ============================================================
        // MATERIAL REPEATER JS (Custom Add & Remove Row UI)
        // ============================================================
        function addMaterialRow(nama = '', jumlah = '') {
            const rowHtml = `
                <div class="material-item-row card mb-2 p-2 shadow-sm border-0 animate__animated animate__fadeIn" style="background: #ffffff; border-radius: 12px; border-left: 4px solid #005eb8 !important;">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-md-7">
                            <label class="small text-muted font-weight-bold mb-1">Nama Material / Pohon</label>
                            <input type="text" class="form-control form-control-sm input-nama-material" value="${nama}" placeholder="Contoh: Isolator Tumpu / Pohon Mangga">
                        </div>
                        <div class="col-4 col-md-4">
                            <label class="small text-muted font-weight-bold mb-1">Jumlah</label>
                            <input type="text" class="form-control form-control-sm input-jumlah-material" value="${jumlah}" placeholder="Contoh: 2 buah / 5 m">
                        </div>
                        <div class="col-2 col-md-1 text-end">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-material border-0" title="Hapus"><i class="fas fa-trash-can"></i></button>
                        </div>
                    </div>
                </div>
            `;
            $('#material-repeater-container').append(rowHtml);
        }

        $('#btn-add-material').click(function() {
            addMaterialRow();
        });

        $(document).on('click', '.btn-remove-material', function() {
            $(this).closest('.material-item-row').remove();
        });

        $('form').on('submit', function() {
            let materialItems = [];
            $('.material-item-row').each(function() {
                const nama = $(this).find('.input-nama-material').val().trim();
                const qty = $(this).find('.input-jumlah-material').val().trim();
                if (nama !== '') {
                    materialItems.push(qty ? `- ${qty} ${nama}` : `- ${nama}`);
                }
            });

            if (materialItems.length > 0) {
                $('#material-hidden-field').val(materialItems.join("\n"));
            } else {
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            }
        });

    });
