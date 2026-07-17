import type { FormEvent, ReactNode } from 'react';
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

interface FormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    submitLabel: string;
    cancelLabel?: string;
    onSubmit: () => void;
    processing?: boolean;
    children: ReactNode;
}

/**
 * A modal wrapping a short form — the shape every "record this action" step in
 * the finance flow needs (pencairan, pembayaran, pengembalian). Submitting via
 * the form element rather than the button keeps Enter working.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    cancelLabel = 'Batal',
    onSubmit,
    processing = false,
    children,
}: FormDialogProps) {
    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="text-[#0E1A3A]">
                            {title}
                        </DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="flex flex-col gap-4 py-4">{children}</div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            {cancelLabel}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner className="size-4" />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default FormDialog;
