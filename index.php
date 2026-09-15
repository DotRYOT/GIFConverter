<?php

declare(strict_types=1);

require_once __DIR__ . '/src/AppConfig.php';

if (!AppConfig::isConfigured(__DIR__)) {
  header('Location: setup.php');
  exit;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Local Video to GIF</title>
  <link rel="icon" type="image/svg+xml" href="public/favicon.svg">
  <link rel="shortcut icon" href="public/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>

<body>
  <main class="wrap">

    <section class="card hero">
      <div class="heroTop">
        <p class="eyebrow">Local Hardware</p>
        <div class="themeControl">
          <label for="themeToggle">Dark mode</label>
          <button type="button" id="themeToggle" class="themeBtn" aria-label="Toggle dark mode">Auto</button>
        </div>
      </div>
      <h1>Video to GIF Converter</h1>
      <p class="sub">Upload, trim, crop, caption &mdash; ffmpeg runs on your machine.</p>
    </section>

    <section class="card">

      <div id="dropzone" class="dropzone" role="button" tabindex="0" aria-label="Drop videos here or click to select">
        <span id="dropzoneLabel">Drop one or more videos here, or <u>click to select</u></span>
        <input type="file" id="videoInput"
          accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska" multiple style="display:none">
      </div>

      <div id="videoInfo" class="videoInfo hidden">
        <span id="infoName" class="infoChip"></span>
        <span id="infoDuration" class="infoChip"></span>
        <span id="infoDims" class="infoChip"></span>
        <span id="infoFps" class="infoChip"></span>
      </div>

      <section id="uploadPanel" class="progressPanel hidden" aria-live="polite">
        <div class="progressHead">
          <span id="uploadLabel">Uploading...</span>
          <span id="uploadPercent">0%</span>
        </div>
        <div class="progressTrack" id="uploadTrack" role="progressbar" aria-valuemin="0" aria-valuemax="100"
          aria-valuenow="0">
          <div id="uploadFill" class="progressFill"></div>
        </div>
      </section>

      <div id="settingsPanel" class="hidden">
        <form id="convertForm">
          <input type="hidden" id="jobId" name="job_id">

          <details class="settingsGroup" open>
            <summary>Trim</summary>
            <div class="trimArea">
              <div class="trimLabels">
                <span>Start: <b id="trimStartLabel">0.0s</b></span>
                <span>Duration: <b id="trimDurLabel">-</b></span>
                <span>End: <b id="trimEndLabel">-</b></span>
              </div>
              <div class="trimSliderWrap" id="trimSliderWrap">
                <div class="trimRangeSelected" id="trimRangeSelected"></div>
                <input type="range" id="trimStart" class="trimSlider" min="0" max="100" step="0.1" value="0">
                <input type="range" id="trimEnd" class="trimSlider" min="0" max="100" step="0.1" value="100">
              </div>
              <input type="hidden" name="start_time" id="startTimeInput" value="0">
              <input type="hidden" name="end_time" id="endTimeInput" value="0">
            </div>
          </details>

          <details class="settingsGroup" open>
            <summary>Output settings</summary>
            <div class="grid">
              <label class="field">
                <span>FPS (5-20)</span>
                <input type="number" name="fps" id="fpsInput" min="5" max="20" value="10">
              </label>
              <label class="field">
                <span>Width px (160-900)</span>
                <input type="number" name="width" id="widthInput" min="160" max="900" value="480">
              </label>
              <label class="field">
                <span>Preset</span>
                <select name="preset" id="presetInput">
                  <option value="small">Small file size</option>
                  <option value="balanced" selected>Balanced</option>
                  <option value="quality">Higher quality</option>
                </select>
              </label>
            </div>
          </details>

          <details class="settingsGroup">
            <summary>Crop &amp; Caption</summary>
            <div class="grid2">
              <label class="field">
                <span>Crop</span>
                <select name="crop" id="cropInput">
                  <option value="">None</option>
                  <option value="square">Square (1:1)</option>
                  <option value="16_9">16:9</option>
                  <option value="4_3">4:3</option>
                  <option value="9_16">9:16 (portrait)</option>
                </select>
              </label>
              <label class="field">
                <span>Caption position</span>
                <select name="caption_pos" id="captionPosInput">
                  <option value="bottom" selected>Bottom</option>
                  <option value="top">Top</option>
                </select>
              </label>
            </div>
            <label class="field">
              <span>Caption text (optional, max 120 chars)</span>
              <input type="text" name="caption" id="captionInput" maxlength="120" placeholder="Enter caption text...">
            </label>
          </details>

          <div class="actionsRow">
            <button type="submit" id="submitBtn" disabled>Convert to GIF</button>
            <button type="button" id="addQueueBtn" class="btnSecondary" disabled>+ Queue</button>
          </div>
        </form>
      </div>

      <p id="status" class="status">Choose a video to begin.</p>

      <section id="progressPanel" class="progressPanel hidden" aria-live="polite">
        <div class="progressHead">
          <span id="progressLabel">Converting...</span>
          <span id="progressPercent">0%</span>
        </div>
        <div class="progressTrack" id="convertTrack" role="progressbar" aria-valuemin="0" aria-valuemax="100"
          aria-valuenow="0" aria-label="Conversion progress">
          <div id="progressFill" class="progressFill"></div>
        </div>
      </section>

      <section id="result" class="result hidden">
        <h2>Done</h2>
        <p id="resultMeta"></p>
        <img id="gifPreview" class="preview" alt="Generated GIF preview">
        <div class="resultActions">
          <a id="downloadLink" class="download" href="#" download="converted.gif">Download GIF</a>
          <button type="button" id="convertAnotherBtn" class="btnSecondary">Convert another</button>
        </div>
      </section>
    </section>

    <section class="card" id="queueCard">
      <h2 class="cardTitle">Batch Queue <span id="queueCount" class="badge">0</span><button type="button" id="downloadAllBtn" class="btnTiny btnDownloadAll" style="display:none">Download All</button></h2>
      <ul id="queueList" class="queueList"></ul>
      <div class="actionsRow" id="queueActions" style="display:none">
        <button type="button" id="processQueueBtn">Process All</button>
        <button type="button" id="clearQueueBtn" class="btnSecondary btnTiny">Clear</button>
      </div>
    </section>

    <section class="card">
      <h2 class="cardTitle">Saved Presets</h2>
      <div class="actionsRow">
        <input type="text" id="presetNameInput" placeholder="Preset name..." style="max-width:200px">
        <button type="button" id="savePresetBtn" class="btnSecondary">Save current</button>
      </div>
      <ul id="presetList" class="presetList"></ul>
    </section>

    <section class="card">
      <h2 class="cardTitle">History</h2>
      <ul id="historyList" class="historyList"></ul>
      <p id="historyEmpty" class="muted">No conversions yet.</p>
    </section>

  </main>
  <script src="public/js/app.js"></script>
</body>

</html>