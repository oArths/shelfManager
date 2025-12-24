import * as I from 'lucide-react'

interface ModalProps {
  Children: React.JSX.Element
  onClose: () => void
  isFull?: boolean
}

export default function Modal(data: ModalProps): React.JSX.Element {
  return (
    <main className=" fixed inset-0 z-20 flex items-center justify-center w-full h-full bg-white_cold/15 backdrop-blur-[10px]">
      <div
        className={` relative p-14 flex  flex-col max-w-[90%] max-h-[90vh] overflow-y-hidden overflow-x-hidden gap-12 bg-white_cold rounded-md border border-border shadow-md ${data.isFull ? 'w-full' : 'w-fit'}`}
      >
        <div
          onClick={data.onClose}
          className="flex rounded-lg w-10 h-10 items-center justify-center bg-border/20 absolute right-4 top-4 cursor-pointer"
        >
          <I.X width="90%" height="90%" stroke="4.36" className="stroke-border " />
        </div>
        {data.Children}
      </div>
    </main>
  );
}
