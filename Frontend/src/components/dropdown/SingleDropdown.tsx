import Dropdown from './index'
import type { SingleDropdownProps } from './interface'

export default function SingleDropdown(
  props: Omit<SingleDropdownProps, 'type'>
): React.JSX.Element {
  return <Dropdown {...props} type="single" />
}
