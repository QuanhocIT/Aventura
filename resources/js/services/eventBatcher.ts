/**
 * Event Batcher Service cho Laravel Reverb / WebSockets.
 * Gom nhiều sự kiện nháy liên tục trong khoảng timeWindowMs (mặc định 250ms)
 * để batch update state của Vue, tránh làm treo main thread UI.
 */

type EventHandler<T = any> = (events: T[]) => void;

class EventBatcher<T = any> {
  private queue: T[] = [];
  private timer: ReturnType<typeof setTimeout> | null = null;
  private handler: EventHandler<T>;
  private timeWindowMs: number;

  constructor(handler: EventHandler<T>, timeWindowMs = 250) {
    this.handler = handler;
    this.timeWindowMs = timeWindowMs;
  }

  public push(event: T): void {
    this.queue.push(event);

    if (!this.timer) {
      this.timer = setTimeout(() => {
        this.flush();
      }, this.timeWindowMs);
    }
  }

  public flush(): void {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }

    if (this.queue.length > 0) {
      const items = [...this.queue];
      this.queue = [];
      try {
        this.handler(items);
      } catch (e) {
        console.error('Error executing batched events:', e);
      }
    }
  }

  public clear(): void {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
    this.queue = [];
  }
}

export function createEventBatcher<T = any>(handler: EventHandler<T>, timeWindowMs = 250): EventBatcher<T> {
  return new EventBatcher<T>(handler, timeWindowMs);
}
