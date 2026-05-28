function closeImageViewer() {
  const viewer = document.getElementById('docs-image-viewer');

  if (viewer) {
    const resizeHandler = viewer.__resizeHandler;
    if (resizeHandler) {
      window.removeEventListener('resize', resizeHandler);
    }

    viewer.remove();
  }

  document.body.classList.remove('docs-image-viewer-open');
}

function clampZoom(value) {
  return Math.min(4, Math.max(0.05, value));
}

function percentToZoom(percent) {
  return clampZoom(percent / 100);
}

function zoomToPercent(zoom) {
  return zoom * 100;
}

function applyZoom(state) {
  state.zoom = clampZoom(state.zoom);
  state.image.style.width = `${Math.round(state.baseWidth * state.zoom)}px`;
  state.image.style.height = `${Math.round(state.baseHeight * state.zoom)}px`;
  state.zoomLabel.textContent = `${Math.round(state.zoom * 100)}%`;
}

function centerInFrame(state) {
  const maxLeft = Math.max(0, state.frame.scrollWidth - state.frame.clientWidth);
  const maxTop = Math.max(0, state.frame.scrollHeight - state.frame.clientHeight);
  state.frame.scrollLeft = maxLeft / 2;
  state.frame.scrollTop = maxTop / 2;
}

function measureBaseSize(image) {
  const naturalWidth = image.naturalWidth || 0;
  const naturalHeight = image.naturalHeight || 0;

  if (naturalWidth > 0 && naturalHeight > 0) {
    return { width: naturalWidth, height: naturalHeight };
  }

  const rect = image.getBoundingClientRect();
  return {
    width: Math.max(1, Math.round(rect.width || 1)),
    height: Math.max(1, Math.round(rect.height || 1)),
  };
}

function fitZoom(state) {
  const frameWidth = state.frame.clientWidth - 16;
  const frameHeight = state.frame.clientHeight - 16;
  const imageWidth = state.image.naturalWidth || state.image.width;
  const imageHeight = state.image.naturalHeight || state.image.height;

  if (!imageWidth || !imageHeight || !frameWidth || !frameHeight) {
    state.zoom = 1;
    applyZoom(state);
    centerInFrame(state);
    return;
  }

  state.zoom = Math.min(1, frameWidth / imageWidth, frameHeight / imageHeight);
  applyZoom(state);
  centerInFrame(state);
}

function openImageViewer(sourceImage) {
  closeImageViewer();

  const viewer = document.createElement('div');
  viewer.id = 'docs-image-viewer';
  viewer.className = 'docs-image-viewer';
  viewer.setAttribute('tabindex', '-1');

  const backdrop = document.createElement('div');
  backdrop.className = 'docs-image-viewer__backdrop';

  const frame = document.createElement('div');
  frame.className = 'docs-image-viewer__frame';

  const canvas = document.createElement('div');
  canvas.className = 'docs-image-viewer__canvas';

  const controls = document.createElement('div');
  controls.className = 'docs-image-viewer__controls';

  const zoomOut = document.createElement('button');
  zoomOut.type = 'button';
  zoomOut.textContent = '-';
  zoomOut.setAttribute('aria-label', 'Zoom out');
  zoomOut.title = 'Zoom out';

  const zoomLabel = document.createElement('span');
  zoomLabel.className = 'docs-image-viewer__zoom-label';
  zoomLabel.textContent = '100%';

  const zoomIn = document.createElement('button');
  zoomIn.type = 'button';
  zoomIn.textContent = '+';
  zoomIn.setAttribute('aria-label', 'Zoom in');
  zoomIn.title = 'Zoom in';

  const zoomReset = document.createElement('button');
  zoomReset.type = 'button';
  zoomReset.textContent = 'Reset';
  zoomReset.setAttribute('aria-label', 'Reset zoom');
  zoomReset.title = 'Reset zoom';

  controls.append(zoomOut, zoomLabel, zoomIn, zoomReset);

  const image = document.createElement('img');
  image.src = sourceImage.currentSrc || sourceImage.src;
  image.alt = sourceImage.alt || 'Documentation image';
  image.loading = 'eager';
  image.className = sourceImage.className;
  image.draggable = false;

  if (sourceImage.classList.contains('mermaid-diagram')) {
    frame.classList.add('docs-image-viewer__frame--light');
  }

  canvas.append(image);
  frame.append(canvas);
  viewer.append(backdrop, controls, frame);
  document.body.appendChild(viewer);
  document.body.classList.add('docs-image-viewer-open');

  const state = {
    image,
    frame,
    zoom: 1,
    zoomLabel,
    baseWidth: 0,
    baseHeight: 0,
  };

  function initBaseSize() {
    const base = measureBaseSize(image);
    state.baseWidth = base.width;
    state.baseHeight = base.height;
  }

  if (image.complete) {
    initBaseSize();
    fitZoom(state);
  } else {
    image.addEventListener(
      'load',
      () => {
        initBaseSize();
        fitZoom(state);
      },
      { once: true },
    );
  }

  zoomIn.addEventListener('click', () => {
    const currentPercent = zoomToPercent(state.zoom);
    const nextPercent = Math.ceil((currentPercent + 5) / 5) * 5;
    state.zoom = percentToZoom(nextPercent);
    applyZoom(state);
    centerInFrame(state);
  });

  zoomOut.addEventListener('click', () => {
    const currentPercent = zoomToPercent(state.zoom);
    const nextPercent = Math.floor((currentPercent - 0.01) / 5) * 5;
    state.zoom = percentToZoom(nextPercent);
    applyZoom(state);
    centerInFrame(state);
  });

  zoomReset.addEventListener('click', () => {
    fitZoom(state);
  });

  controls.addEventListener('pointerdown', (event) => {
    event.stopPropagation();
  });

  frame.addEventListener(
    'wheel',
    (event) => {
      if (!event.ctrlKey && !event.metaKey) {
        return;
      }

      event.preventDefault();
      state.zoom += event.deltaY < 0 ? 0.15 : -0.15;
      applyZoom(state);
      centerInFrame(state);
    },
    { passive: false },
  );

  let isPanning = false;
  let startX = 0;
  let startY = 0;
  let startLeft = 0;
  let startTop = 0;

  frame.addEventListener('pointerdown', (event) => {
    const canPan = frame.scrollWidth > frame.clientWidth || frame.scrollHeight > frame.clientHeight;

    if (event.button !== 0 || !canPan) {
      return;
    }

    isPanning = true;
    startX = event.clientX;
    startY = event.clientY;
    startLeft = frame.scrollLeft;
    startTop = frame.scrollTop;
    frame.classList.add('is-panning');
    frame.setPointerCapture(event.pointerId);
  });

  frame.addEventListener('pointermove', (event) => {
    if (!isPanning) {
      return;
    }

    frame.scrollLeft = startLeft - (event.clientX - startX);
    frame.scrollTop = startTop - (event.clientY - startY);
  });

  function stopPan(event) {
    if (!isPanning) {
      return;
    }

    isPanning = false;
    frame.classList.remove('is-panning');
    frame.releasePointerCapture(event.pointerId);
  }

  frame.addEventListener('pointerup', stopPan);
  frame.addEventListener('pointercancel', stopPan);

  image.addEventListener('dragstart', (event) => {
    event.preventDefault();
  });

  const resizeHandler = () => {
    if (state.zoom <= 1.01) {
      fitZoom(state);
    }
  };

  viewer.__resizeHandler = resizeHandler;
  window.addEventListener('resize', resizeHandler);

  backdrop.addEventListener('click', closeImageViewer);
  viewer.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeImageViewer();
    }
  });

  viewer.focus();
}

function isViewerEligibleImage(image) {
  if (!(image instanceof HTMLImageElement)) {
    return false;
  }

  if (image.closest('.md-header, .md-tabs, .md-nav, .md-search')) {
    return false;
  }

  return !!image.closest('.md-typeset');
}

document.addEventListener('click', (event) => {
  const target = event.target;

  if (!(target instanceof Element)) {
    return;
  }

  const image = target.closest('img');
  if (!image || !isViewerEligibleImage(image)) {
    return;
  }

  event.preventDefault();
  openImageViewer(image);
});
