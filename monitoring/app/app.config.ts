// Field use on tablets: default interactive controls to the larger sizes so
// they meet the 44-48px touch target guideline. Explicit `size` props win.
export default defineAppConfig({
  ui: {
    button: { defaultVariants: { size: 'lg' } },
    input: { defaultVariants: { size: 'lg' } },
    textarea: { defaultVariants: { size: 'lg' } },
    select: { defaultVariants: { size: 'lg' } },
    selectMenu: { defaultVariants: { size: 'lg' } },
    checkbox: { defaultVariants: { size: 'lg' } },
    radioGroup: { defaultVariants: { size: 'lg' } },
  },
})
