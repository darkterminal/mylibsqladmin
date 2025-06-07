import { useEffect } from 'react';

/**
 * React hook that adds an event listener to the window object for the specified
 * event type. The handler is called whenever an event of the specified type is
 * triggered, and is passed the event's detail property as its argument.
 *
 * @param eventType The type of event to listen for.
 * @param handler A function to call whenever the event is triggered.
 * @param options An options object that can be used to modify the event listener's behavior.
 * @returns A cleanup function that can be used to remove the event listener.
 */
export function useCustomEvent<T>(
    eventType: string,
    handler: (detail: T) => void,
    options?: boolean | AddEventListenerOptions
) {
    useEffect(() => {
        const eventListener = (event: Event) => {
            handler((event as CustomEvent<T>).detail);
        };

        window.addEventListener(eventType, eventListener, options);
        return () => window.removeEventListener(eventType, eventListener, options);
    }, [eventType, handler, options]);
}

/**
 * Triggers a custom event with the specified type and detail on the window object.
 *
 * @param eventType The type of event to trigger.
 * @param detail The detail to pass to the event's handler.
 */
export function triggerEvent<T>(eventType: string, detail: T) {
    window.dispatchEvent(new CustomEvent(eventType, { detail }));
}
