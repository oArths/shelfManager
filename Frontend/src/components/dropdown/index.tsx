import * as I from 'lucide-react'
import { useState, useEffect } from 'react'
import { normalizeText } from '../../utils/index'
import type { DropdownProps } from './interface'


export default function Dropdown(props: DropdownProps): React.JSX.Element {
  const [isOpen, setIsOpen] = useState<boolean>(false)
  const [search, setSearch] = useState('')

  const isMulti = props.type === 'multi'
  const isSearchable = props.options.length > 5

  const filteredOptions = props.options.filter(
    (option) => search === '' || normalizeText(option).includes(normalizeText(search))
  )
  //BUG: ajustar o dropdown para que ele nunca fique sem nada selecionado
  const displayValue = isMulti
    ? props.selectedOptions.length > 0
      ? props.selectedOptions.join(', ')
      : props.placeholder || 'Selecione...'
    : props.selectedOption

  const handleOptionClick = (option: string): void => {
    props.onOptionSelect(option, props.filterKey)

    if (!isMulti) {
      setIsOpen(false)
      setSearch('')
    }
  }

  const isOptionSelected = (option: string): boolean => {
    return isMulti ? props.selectedOptions.includes(option) : props.selectedOption === option
  }

  useEffect(() => {
    if (!isOpen) {
      setSearch('')
    }
  }, [isOpen])

  //DONE: FAZER COM QUE AO CLICAR FORA DO DROPDOWN E ELE AINDA ESTIVER ABERTO FAZER ELE FECHAR, USAR A MESMA IDEIA DO BLUR DA MODAL no dropdown
  return (
    <div className=" w-full relative ">
      <div
        onClick={() => !props.disabled && setIsOpen(!isOpen)}
        className={`py-4 text-sm lg:text-base px-5 border bg-secundary bg-white border-border rounded-lg min-w-60  w-full h-10 flex items-center justify-between cursor-pointer select-none ${props.disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
      >
        <span className="truncate flex-1">{displayValue}</span>
        <I.ChevronDown
          height="30px"
          width="30px"
          className={`stroke-text transition ease-linear duration-200 ${isOpen ? 'rotate-180' : 'rotate-0'}`}
        />
      </div>

      {isOpen && (
        <>
          <div className="fixed inset-0 z-50" onClick={() => setIsOpen(false)} />
          <div
            className={`bg-secundary shadow-md border border-border min-w-60 w-full rounded-lg  z-70 ${props.relative ? ' relative top-0.5 ' : ' absolute top-11'}`}
          >
            {isSearchable && (
              <div className="p-3 border-b bg-white rounded-md border-border">
                <input
                  type="text"
                  placeholder="Pesquisar..."
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  className="input p-2 text-sm border border-border rounded-md bg-white outline-none w-full"
                  onClick={(e) => e.stopPropagation()}
                />
              </div>
            )}

            <ul className="flex flex-col max-h-60 overflow-y-auto py-2 bg-white">
              {filteredOptions.length > 0 ? (
                filteredOptions.map((option) => (
                  <li
                    key={`${props.filterKey}-${option}`}
                    onClick={() => handleOptionClick(option)}
                    className="flex flex-row text-sm lg:text-base items-center justify-between py-2 px-3 hover:bg-blue200/10 rounded-md select-none cursor-pointer mx-2"
                  >
                    {option}
                    {isOptionSelected(option) && (
                      <I.Check
                        className="stroke-blue200"
                        height="20px"
                        width="20px"
                        stroke="5.36"
                      />
                    )}
                  </li>
                ))
              ) : (
                <li className="py-2 px-3 text-sm text-gray-500 text-center">
                  Nenhum resultado encontrado
                </li>
              )}
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
