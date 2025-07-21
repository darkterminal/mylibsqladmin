export interface SDKOption {
    id: string
    language: string
    documentation: string
    official?: boolean
}

export const sdkOptions: SDKOption[] = [
    {
        id: "js",
        language: "TypeScript/JS",
        documentation: "https://docs.turso.tech/sdk/ts",
        official: true,
    },
    {
        id: "rust",
        language: "Rust",
        documentation: "https://docs.turso.tech/sdk/rust",
        official: true,
    },
    {
        id: "go",
        language: "Go",
        documentation: "https://docs.turso.tech/sdk/go",
        official: true,
    },
    {
        id: "python",
        language: "Python",
        documentation: "https://docs.turso.tech/sdk/python",
        official: true,
    },
    {
        id: "php",
        language: "PHP",
        documentation: "https://docs.turso.tech/sdk/php",
        official: true,
    },
    {
        id: "ruby",
        language: "Ruby",
        documentation: "https://docs.turso.tech/sdk/ruby",
        official: true,
    },
    {
        id: "active-record",
        language: "ActiveRecord",
        documentation: "https://docs.turso.tech/sdk/activerecord",
        official: true,
    },
    {
        id: "android",
        language: "Android",
        documentation: "https://docs.turso.tech/sdk/android",
        official: true,
    },
    {
        id: "swift",
        language: "Swift",
        documentation: "https://docs.turso.tech/sdk/swift",
        official: true,
    },
    {
        id: "c",
        language: "C",
        documentation: "https://docs.turso.tech/sdk/c",
        official: true,
    },
    {
        id: "flutter",
        language: "Flutter",
        documentation: "https://docs.turso.tech/sdk/flutter",
    },
    {
        id: "php-extension",
        language: "PHP Extension",
        documentation: "https://github.com/tursodatabase/turso-client-php",
    },
    {
        id: "laravel",
        language: "Laravel",
        documentation: "https://github.com/tursodatabase/turso-driver-laravel",
    },
    {
        id: "doctrine",
        language: "Doctrine DBAL",
        documentation: "https://github.com/tursodatabase/turso-doctrine-dbal",
    },
]
