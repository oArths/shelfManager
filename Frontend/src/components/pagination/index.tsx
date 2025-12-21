export interface IPagination {
  limit: number
  total: number
  offset: number
  setOffset: (offset: number) => void
}

export default function Pagination({
  limit,
  total,
  offset,
  setOffset
}: IPagination): React.JSX.Element {
  const max_items = 5
  const current = offset ? offset / limit + 1 : 1
  const pages = Math.ceil(total / limit)
  let start = Math.max(current - Math.floor(max_items / 2), 1)
  const end = Math.min(start + max_items - 1, pages)

  if (end - start < max_items - 1) {
    start = Math.max(end - max_items + 1, 1)
  }
  return (
    <div className=" h-16 w-full flex flex-row items-center justify-between bg-secundary border border-y  border-x-0 border-border  px-7 ">
      <button
        onClick={() => setOffset(Math.max(offset - limit, 0))}
        disabled={current === 1}
        className="w-36 h-7 bg-secundary border border-border text-sm font-normal disabled:cursor-not-allowed disabled:opacity-50"
      >
        Anterior
      </button>
      <span className=" text-white font-normal text-base text-center select-none">
        {current} de {pages}
      </span>
      <button
        onClick={() => setOffset(Math.min(offset + limit, (pages - 1) * limit))}
        disabled={current === pages}
        className="w-36 h-7 bg-secundary border  border-border text-sm font-normal disabled:cursor-not-allowed disabled:opacity-50 "
      >
        Proximo
      </button>
    </div>
  )
}
