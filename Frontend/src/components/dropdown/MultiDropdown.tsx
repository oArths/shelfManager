import Dropdown from './index'
import type { MultiDropdownProps } from './interface'

export default function MultiDropdown(props: Omit<MultiDropdownProps, 'type'>): React.JSX.Element {
  return <Dropdown {...props} type="multi" />
}
