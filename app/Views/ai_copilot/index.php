<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>SIDAK AI Copilot<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK AI Copilot (Enterprise AI Assistant)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 34 SIDAK AI Copilot Design System */
    .copilot-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .copilot-card-chat {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
        height: calc(100vh - 140px);
        min-height: 540px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .chat-messages-box {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #f8fafc;
    }

    /* Chat Bubbles */
    .chat-bubble-user {
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        border-radius: 18px 18px 2px 18px;
        padding: 12px 16px;
        max-width: 75%;
        margin-left: auto;
        margin-bottom: 16px;
        font-size: 13px;
        box-shadow: 0 4px 10px rgba(2, 132, 199, 0.2);
    }

    .chat-card-ai {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 18px;
        max-width: 85%;
        margin-right: auto;
        margin-bottom: 16px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
        border-left: 4px solid #7e22ce;
    }

    .quick-prompt-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 30px;
        padding: 6px 14px;
        font-size: 11px;
        font-weight: 700;
        color: #1e293b;
        transition: all 0.2s ease;
    }
    .quick-prompt-btn:hover {
        background: #f1f5f9;
        border-color: #0284c7;
        color: #0284c7;
    }
</style>

<div class="copilot-container container-fluid py-3">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <span class="fs-2 me-2">🤖</span> SIDAK AI COPILOT
                <span class="badge bg-purple ms-2 rounded-pill font-weight-normal text-white" style="background:#7e22ce; font-size: 10px;">ENTERPRISE V22</span>
            </h3>
            <p class="text-muted small mb-0">Asisten Digital Resmi Berbasis Data Historis, Natural Language Processing & Voice AI Response</p>
        </div>
    </div>

    <!-- Chat Card Container -->
    <div class="copilot-card-chat">
        
        <!-- Messages Area -->
        <div class="chat-messages-box" id="chat-box">
            
            <!-- Welcome AI Card -->
            <div class="chat-card-ai">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple text-white p-2 rounded-circle" style="background:#7e22ce;"><i class="fas fa-robot"></i></span>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">SIDAK AI Copilot</h6>
                        <small class="text-muted" style="font-size: 10px;">Confidence Score: <strong>98%</strong> &middot; Realtime Data Engine</small>
                    </div>
                </div>
                <p class="text-dark small mb-2">
                    Halo <strong class="text-primary"><?= esc($userName) ?></strong> 👋 Saya SIDAK AI Copilot. Ada yang bisa saya bantu hari ini?
                </p>
                <div class="p-2 bg-light rounded-3 border mb-3" style="font-size: 11px;">
                    <i class="fas fa-lightbulb text-warning me-1"></i> <strong>Contoh Pertanyaan:</strong>
                    <ul class="mb-0 ps-3 text-muted">
                        <li>"Hari ini ada berapa Emergency?"</li>
                        <li>"Siapa petugas terbaik bulan ini?"</li>
                        <li>"Penyulang mana paling banyak hotspot?"</li>
                        <li>"Buka Peta GIS"</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Quick Action Prompt Buttons Bar -->
        <div class="p-3 border-top bg-white d-flex align-items-center gap-2 overflow-x-auto">
            <span class="small fw-bold text-muted" style="white-space: nowrap;"><i class="fas fa-bolt text-warning me-1"></i> Quick Action:</span>
            <button class="quick-prompt-btn" onclick="sendQuickPrompt('Berapa Emergency hari ini?')">Emergency Hari Ini</button>
            <button class="quick-prompt-btn" onclick="sendQuickPrompt('Siapa petugas terbaik bulan ini?')">Petugas Terbaik</button>
            <button class="quick-prompt-btn" onclick="sendQuickPrompt('Buka GIS')">Buka GIS</button>
            <button class="quick-prompt-btn" onclick="sendQuickPrompt('Berapa pekerjaan selesai bulan ini?')">Pekerjaan Selesai</button>
            <button class="quick-prompt-btn" onclick="sendQuickPrompt('Asset mana paling berisiko?')">Asset Berisiko</button>
        </div>

        <!-- Input Form Bar with Voice AI Button -->
        <div class="p-3 border-top bg-white">
            <form id="copilot-form" class="d-flex gap-2">
                <button type="button" id="btn-voice-ai" class="btn btn-outline-purple rounded-circle p-2" style="width: 44px; height: 44px; color:#7e22ce; border-color:#7e22ce;" title="Voice AI (Bicara)">
                    <i class="fas fa-microphone fs-5"></i>
                </button>
                <input type="text" id="copilot-input" class="form-control rounded-pill px-3" placeholder="Tanyakan sesuatu pada SIDAK AI Copilot..." required>
                <button type="submit" class="btn btn-primary rounded-pill px-4 font-weight-bold">
                    <i class="fas fa-paper-plane me-1"></i> Kirim
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById('copilot-form');
    var input = document.getElementById('copilot-input');
    var box = document.getElementById('chat-box');
    var voiceBtn = document.getElementById('btn-voice-ai');

    window.sendQuickPrompt = function(text) {
        if (input) {
            input.value = text;
            form.dispatchEvent(new Event('submit'));
        }
    };

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var prompt = input.value.trim();
        if (!prompt) return;

        // Append User Chat Bubble
        var userHtml = '<div class="chat-bubble-user">' + escapeHtml(prompt) + '</div>';
        box.insertAdjacentHTML('beforeend', userHtml);
        box.scrollTop = box.scrollHeight;
        input.value = '';

        // Fetch AI Response via AJAX
        var formData = new FormData();
        formData.append('prompt', prompt);
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        fetch("<?= site_url('ai-copilot/ask') ?>", {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            var aiHtml = '<div class="chat-card-ai">';
            aiHtml += '<div class="d-flex align-items-center gap-2 mb-2">';
            aiHtml += '<span class="badge bg-purple text-white p-2 rounded-circle" style="background:#7e22ce;"><i class="fas fa-robot"></i></span>';
            aiHtml += '<div><h6 class="fw-bold text-dark mb-0">' + (data.title || 'SIDAK AI Copilot') + '</h6>';
            aiHtml += '<small class="text-muted" style="font-size: 10px;">Confidence Score: <strong>' + (data.confidence || 90) + '%</strong></small></div></div>';
            aiHtml += '<p class="text-dark small mb-2">' + (data.body || '') + '</p>';
            if (data.insight) {
                aiHtml += '<div class="p-2 bg-light rounded-3 border mb-2" style="font-size: 11px;"><i class="fas fa-sparkles text-warning me-1"></i> <strong>AI Insight:</strong> ' + data.insight + '</div>';
            }
            if (data.action_url) {
                aiHtml += '<a href="' + data.action_url + '" class="btn btn-xs btn-primary rounded-pill font-weight-bold px-3 me-2 mb-2">' + (data.action_label || 'Buka Action') + ' &rarr;</a>';
            }
            aiHtml += '</div>';

            box.insertAdjacentHTML('beforeend', aiHtml);
            box.scrollTop = box.scrollHeight;

            // Voice Response (Text-To-Speech)
            if ('speechSynthesis' in window && data.body) {
                var cleanText = data.body.replace(/\*\*/g, '');
                var utterance = new SpeechSynthesisUtterance(cleanText);
                utterance.lang = 'id-ID';
                window.speechSynthesis.speak(utterance);
            }
        });
    });

    function escapeHtml(text) {
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    // Voice AI Speech Recognition Listener
    if (voiceBtn) {
        voiceBtn.addEventListener('click', function() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                var recognition = new SpeechRecognition();
                recognition.lang = 'id-ID';
                recognition.start();

                voiceBtn.classList.add('bg-purple', 'text-white');

                recognition.onresult = function(event) {
                    var transcript = event.results[0][0].transcript;
                    input.value = transcript;
                    voiceBtn.classList.remove('bg-purple', 'text-white');
                    form.dispatchEvent(new Event('submit'));
                };
            } else {
                alert('Browser Anda tidak mendukung Web Speech Recognition.');
            }
        });
    }
});
</script>
<?= $this->endSection() ?>
