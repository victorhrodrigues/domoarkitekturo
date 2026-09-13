"use client";

import { useEffect, useRef, useState } from "react";
import { useSmoothProgress } from "@/hooks/useSmoothProgress";
import { BlueprintSvg } from "./BlueprintSvg";

export function Loader() {
  const { progress, isComplete } = useSmoothProgress(2500);
  const [mounted, setMounted] = useState(true);
  const svgRef = useRef<SVGSVGElement | null>(null);

  useEffect(() => {
    if (isComplete) {
      const timer = setTimeout(() => setMounted(false), 700);
      return () => clearTimeout(timer);
    }
  }, [isComplete]);

  useEffect(() => {
    if (!svgRef.current) return;

    const paths = svgRef.current.querySelectorAll("path");
    const totalPaths = paths.length;
    const currentLimit = Math.floor((progress / 100) * totalPaths);

    paths.forEach((path, index) => {
      if (index <= currentLimit) {
        path.style.opacity = "1";
        path.style.strokeDashoffset = "0";
      } else {
        path.style.opacity = "0";
        path.style.strokeDashoffset = "1";
      }
    });
  }, [progress]);

  if (!mounted) return null;

  return (
    <div
      className={`fixed inset-0 z-50 flex items-center justify-center bg-[#f8f9fa] transition-opacity duration-700 ${
        isComplete ? "opacity-0 pointer-events-none" : "opacity-100"
      }`}
    >
      {/* Grade milimetrada de fundo em tela cheia */}
      <div className="absolute inset-0 bg-[linear-gradient(to_right,#00000008_1px,transparent_1px),linear-gradient(to_bottom,#00000008_1px,transparent_1px)] bg-[size:24px_24px]" />

      {/* SVG ocupando toda a tela com enquadramento proporcional */}
      <div className="absolute inset-0 flex items-center justify-center p-4 md:p-10 pointer-events-none">
        <BlueprintSvg svgRef={svgRef} />
      </div>

      {/* Rodapé técnico fixado na parte inferior */}
      <div className="absolute bottom-8 left-6 right-6 mx-auto flex max-w-5xl items-center justify-between border-t border-neutral-200/80 pt-3 font-mono text-xs text-neutral-500">
        <span className="tracking-widest">DESENHANDO ESTRUTURA</span>
        <span className="font-semibold text-neutral-800">{Math.round(progress)}%</span>
      </div>
    </div>
  );
}