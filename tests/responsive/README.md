# Responsive Tests

## Environment notes
Playwright/npm not available in this environment. Use the manual checklist below with Chrome DevTools device mode or test on a real device at http://<IP>/payment/.

## Test viewports
320x568, 360x640, 375x667, 390x844, 414x896, 768x1024, 820x1180, 1024x768, 1280x800, 1440x900, 1920x1080, 2560x1440, landscape 844x390

## Check per page
1. No horizontal scroll: document.documentElement.scrollWidth <= window.innerWidth
2. Menu toggle visible and functional under 1024px
3. Touch targets >=44x44px on <=768px
4. No obvious text overflow/truncation
5. Viewport meta correct
6. No console errors
