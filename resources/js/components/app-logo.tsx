import { Link } from '@inertiajs/react';
import { Gem } from 'lucide-react';

export function AppLogo() {
    return (
        <Link href="/" className="flex items-center gap-2">
            <Gem className="h-6 w-6" />
            <span className="text-lg font-bold">Jewelry Invoice</span>
        </Link>
    );
}
