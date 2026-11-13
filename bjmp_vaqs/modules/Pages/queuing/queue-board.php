<?php
require_once ROOT_PATH . '/helpers/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Queue Board</title>
    <?php include ROOT_PATH . '/modules/layout/bootstrap.php'; ?>
    <style>
      body {
        margin: 0;
        height: 100vh;
        overflow: hidden;
        background:
          linear-gradient(135deg, #f7fbf8 0%, #eef6ff 100%);
        color: #0f172a;
      }
      .board-shell {
        height: 100vh;
        padding: 14px;
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr) auto;
        gap: 10px;
      }
      .board-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        border-radius: 18px;
        background:
          linear-gradient(135deg, #062150, #447A61);
        color: #fff;
        padding: 12px 16px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
      }
      .board-header h1 {
        font-size: clamp(1.35rem, 2.2vw, 2.25rem);
        font-weight: 800;
        margin: 0;
        line-height: 1.05;
      }
      .brand-block {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .brand-logo {
        width: 64px;
        height: 64px;
        object-fit: contain;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.92);
        padding: 1px;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
      }
      .brand-eyebrow {
        text-transform: uppercase;
        letter-spacing: .14em;
        font-size: .66rem;
        font-weight: 800;
        color: rgba(255, 255, 255, .76);
      }
      .board-subtitle {
        color: rgba(255, 255, 255, .78);
        font-size: .9rem;
        font-weight: 600;
      }
      .board-clock {
        text-align: right;
        font-weight: 700;
        color: #fff;
      }
      .board-clock small {
        color: rgba(255, 255, 255, .72) !important;
      }
      .board-card {
        border: 0;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.1);
        overflow: hidden;
      }
      .board-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
      }
      .board-stat {
        border-radius: 14px;
        background: rgba(255,255,255,0.88);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .board-stat-label {
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: .66rem;
        font-weight: 800;
      }
      .board-stat-value {
        font-size: clamp(1.4rem, 2vw, 2.15rem);
        font-weight: 800;
        line-height: 1.05;
      }
      .board-label {
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
      }
      .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
      }
      .section-heading {
        font-size: clamp(1.05rem, 1.5vw, 1.45rem);
        font-weight: 800;
        margin: 0;
      }
      .inside-time {
        color: #062150;
        font-size: 1rem;
        font-weight: 700;
      }
      .waiting-count {
        border-radius: 999px;
        font-size: .78rem;
        padding: 6px 10px;
      }
      .board-main-grid {
        display: grid;
        grid-template-columns: minmax(220px, .72fr) minmax(220px, .72fr) minmax(480px, 1.6fr);
        gap: 10px;
        min-height: 0;
      }
      .board-card .card-body {
        padding: 14px !important;
        height: 100%;
        display: flex;
        flex-direction: column;
        min-height: 0;
      }
      .board-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 8px;
        align-content: start;
        min-height: 0;
        overflow: hidden;
      }
      .queue-item {
        border-radius: 12px;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 8px 10px;
      }
      .inside-item {
        background:
          linear-gradient(135deg, rgba(240, 253, 244, .95), rgba(255, 255, 255, .95));
        border-color: rgba(34, 197, 94, 0.22);
      }
      .queue-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        width: 36px;
        height: 36px;
        border-radius: 999px;
        background: linear-gradient(135deg, #062150, #80A497);
        color: #fff;
        font-weight: 800;
        font-size: .92rem;
        flex: 0 0 auto;
      }
      .inside-pill {
        background: linear-gradient(135deg, #447A61, #80A497);
      }
      .waiting-pill {
        background: linear-gradient(135deg, #062150, #6b7280);
      }
      .queue-name {
        font-size: clamp(.9rem, 1.05vw, 1.12rem);
        font-weight: 800;
        line-height: 1.1;
      }
      .queue-meta {
        color: #64748b;
        font-weight: 600;
        font-size: .76rem;
        line-height: 1.15;
      }
      .privacy-note {
        border-radius: 12px;
        background: rgba(15, 23, 42, 0.05);
        color: #475569;
        padding: 8px 12px;
        font-weight: 700;
        font-size: .82rem;
      }
      .video-frame {
        position: relative;
        flex: 1 1 auto;
        min-height: 0;
        height: 100%;
        border-radius: 14px;
        overflow: hidden;
        background: #08131f;
      }
      .video-frame video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }
      .video-placeholder {
        position: absolute;
        inset: 0;
        display: grid;
        place-content: center;
        text-align: center;
        color: rgba(255,255,255,.84);
        padding: 24px;
        background:
          linear-gradient(135deg, rgba(8, 40, 68, .94), rgba(79, 143, 63, .9)),
          url("<?= htmlspecialchars(BASE_URL . '/assets/images/demo-scene.svg') ?>") center/cover;
      }
      .video-placeholder strong {
        font-size: clamp(1.2rem, 1.6vw, 1.8rem);
      }
      .video-caption {
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
        margin-top: 8px;
      }
      @media (max-width: 991px) {
        body {
          height: auto;
          overflow: auto;
        }
        .board-shell {
          height: auto;
        }
        .board-main-grid {
          grid-template-columns: 1fr;
        }
        .board-stat-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .board-header {
          flex-direction: column;
          align-items: flex-start;
        }
        .board-clock {
          text-align: left;
        }
        .video-frame {
          min-height: 260px;
        }
      }
      @media (max-width: 575px) {
        .board-stat-grid {
          grid-template-columns: 1fr;
        }
        .brand-block {
          align-items: flex-start;
        }
        .brand-logo {
          width: 68px;
          height: 68px;
          border-radius: 18px;
        }
      }
    </style>
</head>
<body>
<div class="board-shell">
    <div class="board-header">
        <div class="brand-block">
            <img
                src="<?= htmlspecialchars(BASE_URL . '/assets/images/demo-mark.svg') ?>"
                alt="Demo ID symbol"
                class="brand-logo"
            >
            <div>
                <div class="brand-eyebrow">ID & Queuing System</div>
                <h1>Visitor Queue Board</h1>
                <div class="board-subtitle">Demo Service Center ID & Queuing System</div>
            </div>
        </div>
        <div class="board-clock">
            <div id="boardClock"></div>
            <small class="text-muted">Auto-refresh every 10 seconds</small>
        </div>
    </div>

    <div class="board-stat-grid">
        <div class="board-stat">
            <div class="board-stat-label">Waiting</div>
            <div class="board-stat-value text-primary" id="boardStatWaiting">0</div>
        </div>
        <div class="board-stat">
            <div class="board-stat-label">Inside</div>
            <div class="board-stat-value text-success" id="boardStatInside">0</div>
        </div>
    </div>

    <div class="board-main-grid">
        <div class="card board-card h-100">
            <div class="card-body">
                    <div class="section-title">
                        <div>
                            <div class="board-label">Now Inside</div>
                            <h2 class="section-heading">Currently Admitted Visitors</h2>
                        </div>
                        <span class="badge bg-success waiting-count" id="insideCount">0 inside</span>
                    </div>
                    <div id="insideList" class="board-list"></div>
            </div>
        </div>

        <div class="card board-card h-100">
            <div class="card-body">
                    <div class="section-title">
                        <div>
                            <div class="board-label">Waiting Queue</div>
                            <h2 class="section-heading">Next In Line</h2>
                        </div>
                        <span class="badge bg-success waiting-count" id="waitingCount">0 waiting</span>
                    </div>
                    <div id="waitingList" class="board-list"></div>
            </div>
        </div>

        <div class="card board-card h-100">
            <div class="card-body">
                <div class="section-title">
                    <div>
                        <div class="board-label">Video Presentation</div>
                        <h2 class="section-heading">While Waiting</h2>
                    </div>
                    <span class="badge bg-primary waiting-count">Live Queue</span>
                </div>
                <div class="video-frame">
                    <video id="queueVideo" autoplay muted loop controls playsinline preload="metadata">
                        <source src="<?= htmlspecialchars(BASE_URL . '/assets/videos/queue-presentation.mp4') ?>" type="video/mp4">
                    </video>
                    <div class="video-placeholder" id="videoPlaceholder">
                        <div>
                            <strong>Video Presentation</strong>
                            <div class="mt-2">Place the waiting-area video at public/assets/videos/queue-presentation.mp4</div>
                        </div>
                    </div>
                </div>
                <div class="video-caption">Video Presentation</div>
            </div>
        </div>
    </div>

    <div class="privacy-note">
Note: Only 10 visitors are permitted inside the facility at any time. Upon the completion and check-out of one visitor, a corresponding slot will be made available for the next visitor in the queue.    </div>
</div>

<script>
const QUEUE_BOARD_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/queue-action') ?>
};
</script>

<script>
function boardEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function boardTimeText(value) {
    if (!value) return 'Waiting for next visitor';
    const date = new Date(value.replace(' ', 'T'));
    return isNaN(date.getTime()) ? value : 'Admitted at ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function boardPublicName(value) {
    const cleaned = String(value || '').replace(/\s+/g, ' ').trim();
    if (!cleaned) return 'Visitor';

    const parts = cleaned.split(' ');
    if (parts.length === 1) return parts[0];

    const first = parts[0];
    const suffixes = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
    const lastCandidate = [...parts].reverse().find(function (part) {
        return !suffixes.includes(part.toLowerCase().replace(',', ''));
    }) || '';
    const initial = lastCandidate ? lastCandidate.charAt(0).toUpperCase() + '.' : '';

    return [first, initial].filter(Boolean).join(' ');
}

function refreshBoardClock() {
    $('#boardClock').text(new Date().toLocaleString());
}

function loadBoardData() {
    $.getJSON(QUEUE_BOARD_BOOTSTRAP.actionUrl, { action: 'public_board_data' }).done(function (res) {
        if (!res.ok) {
            return;
        }

        const insideList = Array.isArray(res.inside_list) ? res.inside_list : (res.inside ? [res.inside] : []);
        const stats = res.stats || {};
        $('#boardStatWaiting').text(stats.waiting_count || 0);
        $('#boardStatInside').text(stats.inside_count || 0);
        $('#insideCount').text(insideList.length + ' inside');

        if (!insideList.length) {
            $('#insideList').html('<div class="text-center text-muted py-4">No visitors currently inside.</div>');
        } else {
            const insideHtml = insideList.map(function (row, index) {
                return `
                    <div class="queue-item inside-item">
                        <div class="d-flex align-items-center gap-2">
                            <span class="queue-pill inside-pill">${index + 1}</span>
                            <div>
                                <div class="queue-name">${boardEscape(boardPublicName(row.display_name || 'Visitor'))}</div>
                                <div class="queue-meta">${boardEscape(boardTimeText(row.time_in || row.display_time))}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#insideList').html(insideHtml);
        }

        const waiting = Array.isArray(res.waiting) ? res.waiting : [];
        $('#waitingCount').text(waiting.length + ' waiting');

        if (!waiting.length) {
            $('#waitingList').html('<div class="text-center text-muted py-4">No visitors waiting in queue.</div>');
        } else {
            const waitingHtml = waiting.map(function (row) {
                return `
                    <div class="queue-item">
                        <div class="d-flex align-items-center gap-2">
                            <span class="queue-pill waiting-pill">${boardEscape(row.position || '-')}</span>
                            <div>
                                <div class="queue-name">${boardEscape(boardPublicName(row.display_name || 'Visitor'))}</div>
                                <div class="queue-meta">Waiting position #${boardEscape(row.position || '-')}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#waitingList').html(waitingHtml);
        }
    });
}

$(function () {
    refreshBoardClock();
    loadBoardData();
    $('#queueVideo').on('loadeddata canplay', function () {
        $('#videoPlaceholder').addClass('d-none');
    }).on('error', function () {
        $('#videoPlaceholder').removeClass('d-none');
    });

    setInterval(refreshBoardClock, 1000);
    setInterval(loadBoardData, 10000);
});
</script>
</body>
</html>
