import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-md">
                <AppLogoIcon className="size-8 fill-current bg-white dark:bg-black border rounded-md p-1" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">MylibSQLAdmin</span>
            </div>
        </>
    );
}
