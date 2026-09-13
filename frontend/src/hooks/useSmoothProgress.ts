"use client";

import { useEffect, useState, useRef } from "react";
import { useProgress } from "@react-three/drei";

export function useSmoothProgress(minDurationMs = 2500, thresholdMs = 150) {
  const { active, progress: rawProgress } = useProgress();
  const [smoothProgress, setSmoothProgress] = useState(0);
  const [isComplete, setIsComplete] = useState(false);

  const startTimeRef = useRef<number | null>(null);
  const progressRef = useRef(0);
  
  // Controle para saber se já definimos se foi "rápido demais" (cache)
  const isCachedRef = useRef<boolean | null>(null);

  useEffect(() => {
    // Se o hook já marcou como completo, não faz mais nada neste ciclo de vida
    if (isComplete) return;

    let frameId: number;

    const tick = (now: number) => {
      if (startTimeRef.current === null) {
        startTimeRef.current = now;
      }
      
      const elapsed = now - startTimeRef.current;
      const prontoDownload = !active && rawProgress === 100;

      // JANELA DE TOLERÂNCIA (CACHE CHECK)
      // Se terminou de baixar ANTES do threshold (ex: 150ms), estava no cache.
      if (prontoDownload && elapsed < thresholdMs && isCachedRef.current === null) {
        isCachedRef.current = true;
        setSmoothProgress(100);
        setIsComplete(true);
        return; // Encerra o loop instantaneamente
      }

      // Se passou do threshold, confirmamos que precisa da animação (não estava no cache)
      if (elapsed >= thresholdMs && isCachedRef.current === null) {
        isCachedRef.current = false;
      }

      // Se foi detectado que NÃO estava no cache, segue com a animação suave de 2.5s
      if (isCachedRef.current === false) {
        const timeRatio = Math.min(elapsed / minDurationMs, 1);
        const teto = prontoDownload ? 100 : 90;
        const target = Math.min(timeRatio * 100, teto);

        const prev = progressRef.current;
        let next = prev + (target - prev) * 0.1;

        if (prontoDownload && next >= 99.5) {
          next = 100;
          setIsComplete(true);
        }

        progressRef.current = next;
        setSmoothProgress(next);

        if (next < 100) {
          frameId = requestAnimationFrame(tick);
        }
      } else if (isCachedRef.current === null) {
        // Ainda estamos dentro da janela de 150ms decidindo o que fazer.
        // Mantém a tela de loading "segurando" o visual em 0% por 1 décimo de segundo.
        frameId = requestAnimationFrame(tick);
      }
    };

    frameId = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frameId);
  }, [active, rawProgress, minDurationMs, thresholdMs, isComplete]);

  return { progress: smoothProgress, isComplete };
}