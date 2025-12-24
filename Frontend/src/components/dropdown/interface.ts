export interface DropdownBaseProps {
  options: string[]
  disabled?: boolean
  filterKey: string
  onOptionSelect: (option: string, filterKey: string) => void
  placeholder?: string
  relative?: boolean
}

export interface SingleDropdownProps extends DropdownBaseProps {
  type: 'single'
  selectedOption: string
}

export interface MultiDropdownProps extends DropdownBaseProps {
  type: 'multi'
  selectedOptions: string[]
}

export type DropdownProps = SingleDropdownProps | MultiDropdownProps
