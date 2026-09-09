/**
 * Turns whatever the SDK, the provider, or the network threw into two answers:
 * is it worth retrying, and what should the user actually read?
 *
 * Provider errors arrive as prose in a dozen shapes, so classification is by
 * pattern. Everything unrecognised is treated as non-retryable — retrying a
 * fault we do not understand mostly wastes the user's time and money.
 */

export interface DescribedError {
  /** One calm sentence for the user. */
  summary: string;
  /** The raw provider text, shown only if they open the details. */
  detail: string;
  retryable: boolean;
}

export function describeError(error: unknown, maxTurns: number): DescribedError {
  const detail = rawMessage(error);
  const name = error instanceof Error ? error.name : '';

  const as = (summary: string, retryable = false): DescribedError => ({ summary, detail, retryable });

  if (name === 'AbortError' || /aborted|cancell?ed/i.test(detail)) {
    return as('اجرا متوقف شد.');
  }

  // The agent calls the model straight from this page, so the endpoint has to
  // allow cross-origin requests. api.openai.com rejects the preflight for
  // authenticated POSTs, which the SDK surfaces as a bare "Connection error" —
  // worth naming, because nothing about that message points at CORS.
  if (/Connection error|Failed to fetch|NetworkError|ERR_NETWORK|CORS/i.test(detail)) {
    return as(
      'ارتباط با اندپوینت مدل برقرار نشد. اگر تکرار شد، بررسی کنید که اندپوینت CORS مرورگر را '
      + 'اجازه می‌دهد — api.openai.com این اجازه را نمی‌دهد.',
      true,
    );
  }

  if (/timeout|timed out|ETIMEDOUT/i.test(detail)) {
    return as('مدل به‌موقع پاسخ نداد.', true);
  }

  if (/\b429\b|rate.?limit|too many requests/i.test(detail)) {
    return as('به سقف نرخ درخواست رسیدیم.', true);
  }

  if (/\b5\d\d\b|server_error|overloaded|service unavailable|bad gateway/i.test(detail)) {
    return as('سرویس مدل موقتاً در دسترس نیست.', true);
  }

  if (/\b401\b|invalid[_ ]api[_ ]key|unauthorized|authentication/i.test(detail)) {
    return as('کلید API پذیرفته نشد. کلید را در پلاگیتی → تنظیمات بررسی کنید.');
  }

  if (/\b403\b|permission|access denied/i.test(detail)) {
    return as('این کلید اجازه‌ی دسترسی به این مدل را ندارد.');
  }

  if (/reasoning_effort|Function tools with reasoning/i.test(detail)) {
    return as(
      'این مدل روی /v1/chat/completions همراه با function tools کار نمی‌کند. '
      + 'سرویس‌دهنده‌ای را انتخاب کنید که Responses API را پشتیبانی کند (مثل AvalAI)، یا مدل دیگری بزنید.',
    );
  }

  if (/\b404\b|model.*not.*found|does not exist|unknown model/i.test(detail)) {
    return as('مدل روی این اندپوینت پیدا نشد. مدل و سرویس‌دهنده را در پلاگیتی → تنظیمات بررسی کنید.');
  }

  if (/context.?length|maximum context|too many tokens|context_length_exceeded/i.test(detail)) {
    return as(
      'گفتگو از ظرفیت مدل بلندتر شده. با دکمه‌ی «چت جدید» یک گفتگوی تازه شروع کنید تا ادامه دهیم.',
    );
  }

  if (/MaxTurns/i.test(name) || /max.?turns/i.test(detail)) {
    return as(`به سقف ${maxTurns} گام رسیدیم بدون اینکه کار تمام شود. پیام بعدی را بفرستید تا ادامه دهد.`);
  }

  if (/ModelBehavior/i.test(name) || /malformed|invalid json|could not parse/i.test(detail)) {
    return as('پاسخ مدل ناقص یا خراب بود.', true);
  }

  if (/Refusal/i.test(name) || /refus/i.test(detail)) {
    return as('مدل از انجام این درخواست خودداری کرد.');
  }

  if (/\b400\b|invalid[_ ]request|bad request/i.test(detail)) {
    return as('درخواست برای این مدل نامعتبر بود.');
  }

  return as('خطای پیش‌بینی‌نشده رخ داد.');
}

/** Digs the useful text out of an SDK/provider error rather than "[object Object]". */
function rawMessage(error: unknown): string {
  if (error instanceof Error) {
    const nested = (error as { cause?: unknown }).cause;
    const causeText = nested instanceof Error ? ` — ${nested.message}` : '';

    return `${error.message || error.name}${causeText}`;
  }

  if (typeof error === 'string') {
    return error;
  }

  try {
    return JSON.stringify(error) ?? String(error);
  } catch {
    return String(error);
  }
}
