import { useEffect, useRef, useState } from 'react';
import SignaturePadLib from 'signature_pad';

export default function SignaturePad({
    onChange,
    disabled = false,
    label = 'Sign inside the box',
}: {
    onChange: (dataUrl: string | null) => void;
    disabled?: boolean;
    label?: string;
}) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const padRef = useRef<SignaturePadLib | null>(null);
    const [hasSignature, setHasSignature] = useState(false);

    useEffect(() => {
        if (!canvasRef.current) return;

        const resize = () => {
            const canvas = canvasRef.current;
            if (!canvas) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d')?.scale(ratio, ratio);
        };

        resize();

        const pad = new SignaturePadLib(canvasRef.current, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(17, 24, 39)',
        });
        padRef.current = pad;

        const handleEnd = () => {
            const empty = pad.isEmpty();
            setHasSignature(!empty);
            onChange(empty ? null : pad.toDataURL('image/png'));
        };

        pad.addEventListener('endStroke', handleEnd);
        window.addEventListener('resize', resize);

        return () => {
            pad.removeEventListener('endStroke', handleEnd);
            window.removeEventListener('resize', resize);
            pad.off();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const clear = () => {
        padRef.current?.clear();
        setHasSignature(false);
        onChange(null);
    };

    return (
        <div>
            <div className="flex items-center justify-between">
                <label className="text-sm font-semibold text-ink-700">{label}</label>
                {!disabled && (
                    <button
                        type="button"
                        onClick={clear}
                        className="text-xs font-semibold text-ink-500 underline hover:text-red-600"
                    >
                        Clear
                    </button>
                )}
            </div>
            <div className="mt-1.5 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-white focus-within:border-brand-500">
                <canvas
                    ref={canvasRef}
                    className={`h-40 w-full touch-none ${disabled ? 'pointer-events-none opacity-60' : 'cursor-crosshair'}`}
                />
            </div>
            <div className="mt-1 flex items-center justify-between">
                <p className="text-xs text-ink-500">Draw your signature above using your mouse or finger.</p>
                {hasSignature && (
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-brand-700">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Signature captured
                    </span>
                )}
            </div>
        </div>
    );
}
