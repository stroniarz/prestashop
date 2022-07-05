{*
*  @author    Martin Tomasek
*  @copyright DiffSolutions, s.r.o.
*  licensed under CC BY-SA 4.0
*}
{literal}
<script>
// Usage: copyToClipboard('Hello');

const copyToClipboard = (function(){
  let textToCopy;
  let clearSelection = false;
  let didCopy = false;
  document.addEventListener('copy', e => {
    if (textToCopy !== undefined) {
      try {
        e.clipboardData.setData('text/plain', textToCopy);
        e.preventDefault();
        didCopy = true;
      } finally {
        textToCopy = undefined;
      }
    }
  });
  return function(text) {
    textToCopy = text;
    if (!document.queryCommandEnabled('copy')) {
      // See: https://bugs.webkit.org/show_bug.cgi?id=156529
      const sel = document.getSelection();
      const range = document.createRange();
      range.selectNodeContents(document.body);
      sel.addRange(range);
      clearSelection = true;
    }
    didCopy = false;
    document.execCommand('copy');
    if (clearSelection) {
      clearSelection = false;
      document.getSelection().removeAllRanges();
    }
    return didCopy;
  };
})();
</script>
{/literal}
