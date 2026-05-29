# `templates/components/`

Reusable Twig partials used by the staff-admin UI shell and feature
pages. Every component is invoked with `{% include ... with { ... } only %}`
so callers pass parameters explicitly — no globals, no implicit context.

| Component | Parameters | One-line usage |
| --- | --- | --- |
| `_main_nav.html.twig` | _(no parameters; reads `main_navigation()` from `App\Ui\Twig\NavigationExtension`)_ | `{% include 'components/_main_nav.html.twig' %}` |
| `_kpi_card.html.twig` | `string label`, `string value`, `?string delta` | `{% include 'components/_kpi_card.html.twig' with { label: 'Today\'s Revenue', value: '$4,182.50', delta: '+12% vs. yesterday' } only %}` |
| `_page_header.html.twig` | `string title`, `?string actions` _(pre-rendered HTML, rendered raw)_ | `{% include 'components/_page_header.html.twig' with { title: 'Admin Dashboard', actions: actionsHtml } only %}` |
| `_badge.html.twig` | `string label`, `string variant` ∈ `success \| warning \| danger \| info \| neutral`, `?string class` | `{% include 'components/_badge.html.twig' with { label: 'Excellent', variant: 'success' } only %}` |
| `_status_badge.html.twig` | `string status` ∈ `succeeded \| pending \| failed \| refunded` _(maps onto `_badge`)_ | `{% include 'components/_status_badge.html.twig' with { status: tx.status.value } only %}` |
| `_empty_state.html.twig` | `string title`, `string message`, `?string ctaLabel`, `?string ctaRoute` | `{% include 'components/_empty_state.html.twig' with { title: 'Coming soon', message: 'This screen arrives later.' } only %}` |
| `_breadcrumbs.html.twig` | `array trail` of `{ label, route }` items | `{% include 'components/_breadcrumbs.html.twig' with { trail: [{label:'Dashboard',route:'app_dashboard'},{label:'Reports'}] } only %}` |
| `_icon.html.twig` | `icon(string name, int size = 16, number stroke = 1.6, string class = '')` _(Twig macro — imported, not included)_ | `{% import 'components/_icon.html.twig' as icon %}` then `{{ icon.icon('leaf', 18) }}` |

## Icons (`_icon.html.twig`)

A curated SVG line-icon set on a 24x24 grid with a 1.6 default stroke. Unlike
the other components, it is a **Twig macro**, so import it once per template
and call it as a function:

```twig
{% import 'components/_icon.html.twig' as icon %}
{{ icon.icon('bell', 17) }}
{{ icon.icon('leaf', 18, 1.6, 'text-litrec-secondary') }}
```

- Icons inherit color via `stroke="currentColor"` — pass a `text-*` utility in
  the `class` argument to recolor, or a transform utility (e.g. `rotate-90`).
- They are decorative (`aria-hidden="true"`). When an icon is a control's only
  visible content, label the control (`aria-label`), not the icon.
- An unknown name renders an empty, invisible `<svg>` instead of erroring.
- Available names: `search`, `trash`, `plus`, `chevron`, `chevronUp`,
  `chevronR`, `user`, `users`, `cart`, `info`, `bell`, `leaf`, `tree`,
  `calendar`, `heart`, `money`, `tag`, `ticket`, `key`, `arrowUp`, `bolt`,
  `pin`, `check`, `grid`, `clock`, `print`, `sun`, `moon`.

## `lr-` component classes

Beyond the Twig partials, the Eagleton component layer ships as token-driven
CSS classes in `assets/styles/app.css` (under `@layer components`). Compose
them directly in markup; they are global (not scoped to `.lr-screen`) and
recolor automatically with the active theme.

- **Buttons:** `lr-btn` with modifiers `lr-btn-ghost`, `lr-btn-primary`,
  `lr-btn-secondary`, `lr-btn-danger`, `lr-btn-lg`, `lr-btn-block`.
- **Cards:** `lr-card` + `lr-card-head`, `lr-card-title`, `lr-card-body`.
- **Badges:** prefer the `_badge.html.twig` partial; the underlying classes are
  `lr-badge` + a variant (`success`, `warning`, `danger`, `info`, `neutral`).
- **Chips:** `lr-chip`. **Tabs:** `lr-tabs` + `lr-tab` (`is-active`).
- **Icon button:** `lr-iconbtn` (`danger` variant). **Kbd:** `lr-kbd`.
- **Text helpers:** `lr-muted`, `lr-link`, `lr-num` (tabular figures),
  `lr-row-strong`, `lr-section-label`.

Do not transition `var()`-backed color/background/border on these classes — a
theme switch would strand the old value (see the note in `app.css`).

## Conventions

- Components are presentation-only — no service calls, no business logic.
- Optional parameters always have an explicit `{% set foo = foo|default(null) %}`
  at the top so callers can omit them without raising `UndefinedVariable`.
- The `_main_nav` component is the one exception that reads from a Twig
  function instead of receiving parameters; its source of truth is
  `App\Ui\Navigation\MainNavigation` and the structure is intentionally
  centralised, not passed through every render.

## Member Lookup Dialog (LRA-46)

Reusable HTMX/Alpine dialog for picking a household member from anywhere in
the staff-admin UI. The component renders the shared `_modal.html.twig`
shell, a five-field filter form (`code`, `lastName`, `firstName`, `phone`,
`email`), and an HTMX-swappable results region backed by
`GET /admin/users/_lookup` (route name: `member_lookup_search`).

- **Import path:** `components/member_lookup_dialog.html.twig`
- **Props:** none — embed the template once on any page that needs the dialog.
- **Results partial:** `components/member_lookup_dialog/_results.html.twig`
  (rendered by `MemberLookupController` into `#member-lookup-results`).

### Exposed events

The dialog dispatches a one-shot `member-selected` `CustomEvent` on `window`
when a row is clicked, then closes itself. The `detail` shape is:

```js
{
    memberId: '019571bf-…',     // UUID v7 of the member
    householdId: '019571bf-…',  // UUID v7 of the owning household
    fullName: 'Alice Smith',    // display name from the read model
    code: 'M000010',            // member code
}
```

### Usage

Embed the dialog once on the page, then call the JS hook from any trigger:

```twig
{# In your page template, after page_body content: #}
{% include 'components/member_lookup_dialog.html.twig' %}
```

```html
<button
    type="button"
    onclick="openMemberLookup({
        onSelected: function (payload) {
            console.log('selected', payload.memberId, payload.fullName);
        }
    })"
>
    Pick a member
</button>
```

`openMemberLookup` subscribes the callback to `member-selected` for exactly
one fire and then unsubscribes, so callers do not need to manage listeners.
Focus, ESC, backdrop close, and focus return are inherited from the shared
modal shell.
