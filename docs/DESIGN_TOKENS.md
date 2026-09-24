# Rocket Coding Design Tokens

Frontend design system. **Follow these exactly** — visual consistency matters.

---

## Colors

### Primary
| Token | Hex | Usage |
|-------|-----|-------|
| `--rocket-primary` | `#1E40AF` | Buttons, active states, primary actions |
| `--rocket-primary-dark` | `#1E3A8A` | Hover/pressed |
| `--rocket-primary-light` | `#DBEAFE` | Backgrounds, selected states |

### Neutrals
| Token | Hex | Usage |
|-------|-----|-------|
| `--bg` | `#F0EEF6` | App background |
| `--card-bg` | `#FFFFFF` | Card interior |
| `--border` | `#DBE3F4` | Card borders, dividers |
| `--separator` | `#E0E7FF` | Header separators, inline dividers |
| `--text-primary` | `#1F2937` | Body text |
| `--text-secondary` | `#6B7280` | Secondary text, labels |
| `--text-muted` | `#9CA3AF` | Disabled, placeholder |

### Status
| Token | Hex | Usage |
|-------|-----|-------|
| `--status-success` | `#16A34A` | Coded, approved, paid |
| `--status-warning` | `#F59E0B` | Pending, incomplete |
| `--status-error` | `#DC2626` | Denied, rejected, error |
| `--status-info` | `#2563EB` | Informational |

---

## Typography

### Font Stack
- **Display (headlines):** DM Sans
- **Body:** DM Sans (same family — consistent look)
- **Monospace (codes, IDs):** ui-monospace, SFMono-Regular, Menlo, monospace

### Scale
| Use | Size | Weight |
|-----|------|--------|
| H1 | 24px | 600 |
| H2 | 20px | 600 |
| H3 | 16px | 600 |
| Body | 13px | 400 |
| Small | 11px | 400 |
| Tiny/Label | 10px | 500 |

---

## Components

### Buttons — STANDARD
```js
{
  height: 28,
  fontSize: 10,
  padding: "0 12px",
  borderRadius: 6,
  fontWeight: 500,
  fontFamily: "DM Sans"
}
```

### Cards — STANDARD
```js
{
  background: "#FFFFFF",
  border: "1px solid #DBE3F4",
  borderLeft: "3px solid #1E40AF",  // Rocket navy
  borderRadius: 12,
  padding: 16
}
```

### Inputs
```js
{
  height: 32,
  border: "1px solid #DBE3F4",
  borderRadius: 6,
  padding: "0 10px",
  fontSize: 12,
  fontFamily: "DM Sans"
}
```

---

## Logo

🚀 navy gradient square, corner-rounded 8px.

Gradient:
```css
background: linear-gradient(135deg, #1E40AF 0%, #1E3A8A 100%);
```

---

## Anti-patterns — Do Not

- Don't use JSX in large prototype artifacts. Use `React.createElement` with `var ce = React.createElement;` at top.
- Don't use `let` or `const` in prototype artifacts — use `var`.
- Don't introduce new colors without adding to this file first.
- Don't use Tailwind classes inline in prototype artifacts — use inline style objects.
- Don't add box-shadows on cards — the design is flat.
- Don't round buttons more than 6px.

---

## Prototype Starter Snippet

```html
<script>
  var ce = React.createElement;
  var TOKENS = {
    bg: "#F0EEF6",
    cardBg: "#FFFFFF",
    border: "#DBE3F4",
    primary: "#1E40AF",
    textPrimary: "#1F2937",
    textSecondary: "#6B7280"
  };

  function Button(props) {
    return ce("button", {
      style: {
        height: 28,
        fontSize: 10,
        padding: "0 12px",
        borderRadius: 6,
        background: TOKENS.primary,
        color: "#fff",
        border: "none",
        fontFamily: "DM Sans",
        cursor: "pointer"
      },
      onClick: props.onClick
    }, props.label);
  }
</script>
```
