/** see README.md */
function legacy(text) {
  const area = document.createElement('textarea')

  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.opacity = '0'
  document.body.append(area)
  area.select()

  let copied = false

  try {
    copied = document.execCommand('copy')
  } catch {
    copied = false
  }

  area.remove()

  return copied
}

export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text)

    return true
  } catch {
    return legacy(text)
  }
}
