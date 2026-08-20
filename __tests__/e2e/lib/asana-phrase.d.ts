declare module 'asana-phrase' {
    class Factory {
        constructor(generators: unknown[]);
        randomPhrase(): string[];
    }

    export function default32BitFactory(): Factory;
    export { Factory /*, WordGenerator, Dictionary, NumberRange, dictionaries */ };
}
