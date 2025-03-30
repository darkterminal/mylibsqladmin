import { useSyncExternalStore } from 'react';

type Signal<T> = {
    value: T;
    subscribe: (listener: () => void) => () => void;
};

export function createSignal<T>(initialValue: T): Signal<T> {
    let value = initialValue;
    const subscribers = new Set<() => void>();

    return {
        get value() {
            return value;
        },
        set value(newValue: T) {
            if (value !== newValue) {
                value = newValue;
                subscribers.forEach((subscriber) => subscriber());
            }
        },
        subscribe: (listener: () => void) => {
            subscribers.add(listener);
            return () => subscribers.delete(listener);
        },
    };
}

export function useSignal<T>(signal: Signal<T>): [T, (value: T) => void] {
    const state = useSyncExternalStore(
        (listener) => signal.subscribe(listener),
        () => signal.value
    );

    const setValue = (newValue: T) => {
        signal.value = newValue;
    };

    return [state, setValue];
}
