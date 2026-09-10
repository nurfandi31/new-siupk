---
name: Rural Prosperity Framework
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#42474e'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#73777f'
  outline-variant: '#c2c7cf'
  surface-tint: '#38618c'
  primary: '#002746'
  on-primary: '#ffffff'
  primary-container: '#0b3d66'
  on-primary-container: '#81a8d7'
  inverse-primary: '#a2cafa'
  secondary: '#006d3d'
  on-secondary: '#ffffff'
  secondary-container: '#97f3b5'
  on-secondary-container: '#047240'
  tertiary: '#372100'
  on-tertiary: '#ffffff'
  tertiary-container: '#543400'
  on-tertiary-container: '#dd9729'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d1e4ff'
  primary-fixed-dim: '#a2cafa'
  on-primary-fixed: '#001d36'
  on-primary-fixed-variant: '#1d4973'
  secondary-fixed: '#9af6b8'
  secondary-fixed-dim: '#7ed99e'
  on-secondary-fixed: '#00210f'
  on-secondary-fixed-variant: '#00522d'
  tertiary-fixed: '#ffddb5'
  tertiary-fixed-dim: '#ffb956'
  on-tertiary-fixed: '#2a1800'
  on-tertiary-fixed-variant: '#633f00'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display-financial:
    fontFamily: Inter
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  data-tabular:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '500'
    lineHeight: 20px
  label-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 1.5rem
  margin-desktop: 2rem
  margin-mobile: 1rem
  stack-sm: 0.5rem
  stack-md: 1rem
  stack-lg: 2rem
---

## Brand & Style

The design system is engineered for BUMDesma/LKD, focusing on financial stewardship and communal growth. The brand personality is **Institutional yet Accessible**, bridging the gap between professional banking standards and village-level usability. 

The design style follows a **Corporate / Modern** aesthetic with a strong emphasis on **Tonal Clarity**. It utilizes high-quality whitespace to reduce cognitive load during complex data entry. The emotional response should be one of security, reliability, and modern efficiency, ensuring users feel their community's financial assets are managed with precision.

## Colors

The palette is anchored in trust and growth. **Navy Blue** serves as the primary driver for navigation, primary actions, and institutional branding. **Forest Green** is reserved strictly for positive financial indicators, successful transactions, and growth-oriented data points. 

**Gold** acts as a strategic accent for highlights, pending statuses, or "prosperity" milestones, while **Red** is utilized with high contrast against the light background for overdue payments and critical system alerts. The neutral background (#F7F9FB) is slightly cool-toned to maintain a crisp, clean environment that reduces eye strain during long periods of auditing.

## Typography

The design system exclusively utilizes **Inter** for its exceptional legibility in data-heavy environments and its neutral, systematic character. 

For financial figures and large currency displays, use the `display-financial` role with a Bold (700) weight and tighter letter spacing to create a sense of importance and solidity. Body text follows a standard scale, while `data-tabular` is optimized for alignment within ledgers and tables. All labels should be rendered in Medium or Semi-bold to ensure they remain legible even at smaller sizes against colored backgrounds.

## Layout & Spacing

This design system employs a **Fixed Grid** model for desktop to ensure financial dashboards remain legible and structured, transitioning to a **Fluid Grid** for mobile devices. 

- **Desktop:** 12-column grid, 1280px max-width, 24px (1.5rem) gutters.
- **Tablet:** 8-column grid, 1.5rem gutters, 2rem side margins.
- **Mobile:** 4-column grid, 1rem gutters, 1rem side margins.

Spacing follows an 8px base unit. Vertical rhythm is maintained through "stacks" where related elements (like an input and its label) use `stack-sm`, while distinct sections use `stack-lg`. Generous padding is required inside cards (min 24px) to ensure data doesn't feel cramped.

## Elevation & Depth

Visual hierarchy is established using **Tonal Layers** combined with **Ambient Shadows**. 

The background (#F7F9FB) sits at the lowest level. Content containers (Cards) are pure white (#FFFFFF) with a very soft, diffused shadow: `0px 4px 20px rgba(11, 61, 102, 0.05)`. This subtle navy-tinted shadow ensures the "trust" color is felt even in the depth model.

Active elements like modals or dropdowns should use a slightly more pronounced elevation to appear closer to the user, while interactive items (buttons) should have a slight "lift" on hover to provide tactile feedback. Avoid heavy borders; use subtle 1px strokes in a light neutral gray only when necessary for tabular separation.

## Shapes

The design system uses a **Rounded** shape language to soften the institutional nature of financial management, making it feel approachable. 

The standard corner radius for primary UI elements (buttons, inputs, small cards) is **12px (rounded-xl)**. Large containers or dashboard sections may use **16px** to emphasize their structural role. Selection indicators (like radio buttons or active tab markers) should remain consistent with this rounded language, avoiding sharp corners entirely.

## Components

### Buttons
- **Primary:** Navy Blue background, white text, 12px radius, bold weight.
- **Secondary:** White background, 1px Navy Blue border, Navy Blue text.
- **Ghost:** No background or border, Navy Blue text, used for less frequent actions.

### Inputs & Fields
- Use a light gray border (1px) that turns Navy Blue on focus. Labels must be positioned above the field using `label-sm`. Error states must use the Danger Red for both the border and a small helper text below.

### Cards
- Pure white background, 12px or 16px radius.
- Use a "Header" section within the card for titles, often separated by a very faint horizontal line.

### Data Tables
- Header row: Light gray background (#F1F4F7), Bold text.
- Rows: White background with a subtle hover state.
- Numeric columns should be right-aligned for easier comparison.

### Progress Indicators
- For loan repayments or budget tracking, use thick, rounded progress bars using Forest Green for "Met" and Navy Blue for "Current."

### Icons
- Use minimalist, 2px stroke line icons. Icons should be monochrome (Navy) unless indicating a specific status (Green for success, Red for alert).