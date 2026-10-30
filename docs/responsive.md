# Responsive Design Documentation

## Breakpoints
- xs: <= 480px (small phones)
- sm: <= 640px (phones)
- md: <= 768px (large phones/small tablets portrait)
- lg: <= 1024px (tablets - sidebar becomes drawer)
- xl: <= 1280px (small laptops)
- 2xl: >= 1536px (large screens, content max-width ~1440px)

## Components
- **Topbar**: Visible on < 1024px with menu toggle, page title, notifications
- **Sidebar**: Fixed on >=1025px (260px), drawer on <1024px (max 300px width)
- **Menu Toggle**: Accessible button with SVG icon, 44x44px minimum, aria attributes
- **Overlay**: Closes menu on click/tap
- **Responsive tables**: Wrapped in .table-responsive with horizontal scroll
- **Modals**: Full screen on small devices, scrollable
- **Forms**: Single column on mobile, stacked buttons

## Navigation behavior
- Click hamburger menu to open sidebar drawer
- Click overlay or press Escape to close
- Click nav link closes menu on mobile
- Focus trap in open menu
- Body scroll locked when menu open
- Menu closes on resize to desktop
- ARIA expanded state updated

## CSS Files
- `assets/css/app.css` - Core styles, variables, components
- `assets/css/responsive.css` - Breakpoint-specific rules
- Role-specific styles.css files remain for compatibility

## Testing
Use Chrome DevTools Device Mode or test on real device. Check for horizontal scroll, menu functionality, touch targets.
