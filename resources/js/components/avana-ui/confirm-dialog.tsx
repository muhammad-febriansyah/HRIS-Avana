import { Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    onConfirm: () => void;
    loading?: boolean;
}

/**
 * Confirmation dialog built on the shadcn `dialog`. Used for delete/approve
 * confirmations. The destructive variant shows a red confirm button with a
 * Trash2 icon.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Batal',
    destructive = false,
    onConfirm,
    loading = false,
}: ConfirmDialogProps) {
    const resolvedConfirmLabel =
        confirmLabel ?? (destructive ? 'Hapus' : 'Konfirmasi');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                {destructive && (
                    <div className="flex size-12 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <Trash2 className="size-5" />
                    </div>
                )}
                <DialogHeader>
                    <DialogTitle className="text-[#0E1A3A]">
                        {title}
                    </DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={loading}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        disabled={loading}
                    >
                        {loading ? (
                            <Spinner className="size-4" />
                        ) : destructive ? (
                            <Trash2 className="size-4" />
                        ) : null}
                        {resolvedConfirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default ConfirmDialog;
