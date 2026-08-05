import { Head } from '@inertiajs/react'

import { AiDocs } from '@ai-docs'

/** see README.md */
export default function AiDocsPage(props) {
  return (
    <>
      <Head title={props.page?.title ?? props.brand?.name ?? 'Docs'} />
      <AiDocs {...props} />
    </>
  )
}
