/**
 * A deliberately small Markdown renderer covering what a coding agent actually
 * emits: fenced code, inline code, bold/italic, headings, lists, links.
 *
 * Everything is HTML-escaped before any markup is introduced, so model output —
 * which is untrusted text — can never inject nodes into the page.
 */

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderInline(text: string): string {
  return text
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\(((?:https?:|\/)[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
}

export function renderMarkdown(source: string): string {
  const escaped = escapeHtml(source);
  const out: string[] = [];
  const lines = escaped.split('\n');

  let inCode = false;
  let codeLang = '';
  let codeBuffer: string[] = [];
  let listType: 'ul' | 'ol' | null = null;
  let paragraph: string[] = [];

  const flushParagraph = () => {
    if (paragraph.length) {
      out.push(`<p>${renderInline(paragraph.join(' '))}</p>`);
      paragraph = [];
    }
  };

  const flushList = () => {
    if (listType) {
      out.push(`</${listType}>`);
      listType = null;
    }
  };

  for (const line of lines) {
    const fence = line.match(/^\s*```(\w*)\s*$/);

    if (fence) {
      if (inCode) {
        out.push(`<pre class="pi-code" data-lang="${codeLang}"><code>${codeBuffer.join('\n')}</code></pre>`);
        codeBuffer = [];
        inCode = false;
      } else {
        flushParagraph();
        flushList();
        inCode = true;
        codeLang = fence[1] || '';
      }
      continue;
    }

    if (inCode) {
      codeBuffer.push(line);
      continue;
    }

    if (line.trim() === '') {
      flushParagraph();
      flushList();
      continue;
    }

    const heading = line.match(/^(#{1,4})\s+(.*)$/);
    if (heading) {
      flushParagraph();
      flushList();
      const level = Math.min(heading[1].length + 2, 6);
      out.push(`<h${level}>${renderInline(heading[2])}</h${level}>`);
      continue;
    }

    const bullet = line.match(/^\s*[-*+]\s+(.*)$/);
    const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);

    if (bullet || numbered) {
      flushParagraph();
      const wanted = bullet ? 'ul' : 'ol';
      if (listType !== wanted) {
        flushList();
        out.push(`<${wanted}>`);
        listType = wanted;
      }
      out.push(`<li>${renderInline((bullet ?? numbered)![1])}</li>`);
      continue;
    }

    flushList();
    paragraph.push(line.trim());
  }

  // An unterminated fence is normal mid-stream — render what we have so far.
  if (inCode && codeBuffer.length) {
    out.push(`<pre class="pi-code" data-lang="${codeLang}"><code>${codeBuffer.join('\n')}</code></pre>`);
  }

  flushParagraph();
  flushList();

  return out.join('\n');
}

export { escapeHtml };
