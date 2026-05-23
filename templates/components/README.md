# `templates/components/`

Reusable Twig partials used by the staff-admin UI shell and feature
pages. Every component is invoked with `{% include ... with { ... } only %}`
so callers pass parameters explicitly — no globals, no implicit context.

| Component | Parameters | One-line usage |
| --- | --- | --- |
| `_main_nav.html.twig` | _(no parameters; reads `main_navigation()` from `App\Ui\Twig\NavigationExtension`)_ | `{% include 'components/_main_nav.html.twig' %}` |
| `_kpi_card.html.twig` | `string label`, `string value`, `?string delta` | `{% include 'components/_kpi_card.html.twig' with { label: 'Today\'s Revenue', value: '$4,182.50', delta: '+12% vs. yesterday' } only %}` |
| `_page_header.html.twig` | `string title`, `?string actions` _(pre-rendered HTML, rendered raw)_ | `{% include 'components/_page_header.html.twig' with { title: 'Admin Dashboard', actions: actionsHtml } only %}` |
| `_status_badge.html.twig` | `string status` ∈ `succeeded \| pending \| failed \| refunded` | `{% include 'components/_status_badge.html.twig' with { status: tx.status.value } only %}` |
| `_empty_state.html.twig` | `string title`, `string message`, `?string ctaLabel`, `?string ctaRoute` | `{% include 'components/_empty_state.html.twig' with { title: 'Coming soon', message: 'This screen arrives later.' } only %}` |
| `_breadcrumbs.html.twig` | `array trail` of `{ label, route }` items | `{% include 'components/_breadcrumbs.html.twig' with { trail: [{label:'Dashboard',route:'app_dashboard'},{label:'Reports'}] } only %}` |

## Conventions

- Components are presentation-only — no service calls, no business logic.
- Optional parameters always have an explicit `{% set foo = foo|default(null) %}`
  at the top so callers can omit them without raising `UndefinedVariable`.
- The `_main_nav` component is the one exception that reads from a Twig
  function instead of receiving parameters; its source of truth is
  `App\Ui\Navigation\MainNavigation` and the structure is intentionally
  centralised, not passed through every render.
